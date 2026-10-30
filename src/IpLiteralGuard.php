<?php

declare(strict_types=1);

namespace Dzentota\HttpClient;

use Dzentota\DnsResolver\SecurityException;

/**
 * Validates URL hosts that are IP literals (which bypass DNS resolution) against
 * a blocklist of private, loopback, link-local, multicast and reserved ranges.
 *
 * Handles canonical IPv4, bracketed IPv6 (incl. zone IDs), and the non-canonical
 * IPv4 forms accepted by inet_aton()/libc (decimal, octal, hex, 1-3 part short forms),
 * plus IPv4 embedded in IPv6 (mapped, compatible, NAT64, 6to4). Teredo is blocked outright.
 */
final class IpLiteralGuard
{
    /**
     * @var array<array{string,int}> 
     */
    private const BLOCKED_V4 = [
        ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8],
        ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24],
        ['192.88.99.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24],
        ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
    ];

    /**
     * @var array<array{string,int}> 
     */
    private const BLOCKED_V6 = [
        ['::', 128], ['::1', 128], ['100::', 64], ['2001::', 32], ['2001:db8::', 32],
        ['2001:10::', 28], ['2001:20::', 28], ['fc00::', 7], ['fe80::', 10], ['fec0::', 10], ['ff00::', 8],
    ];

    /**
     * @throws SecurityException if $host is an IP literal in a blocked range
     */
    public static function assertSafe(string $host): void
    {
        $packed = self::parse($host);
        if ($packed === null) {
            return; // not an IP literal: left to the DNS resolver
        }
        if (self::isBlocked($packed)) {
            throw new SecurityException("Access to IP address '$host' is blocked by SSRF protection");
        }
    }

    /**
     * Returns packed (4 or 16 byte) address if $host is an IP literal, null otherwise.
     *
     * @throws SecurityException for ambiguous numeric hosts that cannot be parsed safely
     */
    public static function parse(string $host): ?string
    {
        $host = strtolower(rtrim($host, '.'));

        if (str_starts_with($host, '[') || str_contains($host, ':')) {
            $inner = trim($host, '[]');
            $zone = strpos($inner, '%');
            if ($zone !== false) {
                $inner = substr($inner, 0, $zone);
            }
            $packed = @inet_pton($inner);
            if ($packed === false || strlen($packed) !== 16) {
                throw new SecurityException("Invalid IPv6 literal '$host'");
            }
            return $packed;
        }

        // inet_aton-compatible: 1-4 parts, each decimal, octal (leading 0) or hex (0x)
        if (!preg_match('/^(0x[0-9a-f]*|\d+)(\.(0x[0-9a-f]*|\d+)){0,3}$/', $host)) {
            return null;
        }
        $parts = explode('.', $host);
        $nums = [];
        foreach ($parts as $p) {
            if (str_starts_with($p, '0x')) {
                $digits = substr($p, 2);
                $n = $digits === '' ? 0 : (strlen(ltrim($digits, '0')) > 8 ? null : hexdec($digits));
            } elseif (strlen($p) > 1 && $p[0] === '0') {
                if (!preg_match('/^[0-7]+$/', $p)) {
                    throw new SecurityException("Ambiguous numeric host '$host'");
                }
                $n = strlen(ltrim($p, '0')) > 11 ? null : octdec($p);
            } else {
                $n = strlen($p) > 10 ? null : (int) $p;
            }
            if ($n === null) {
                throw new SecurityException("Ambiguous numeric host '$host'");
            }
            $nums[] = (int) $n;
        }
        $last = array_pop($nums);
        foreach ($nums as $n) {
            if ($n > 255) {
                throw new SecurityException("Ambiguous numeric host '$host'");
            }
        }
        $lastBits = 8 * (4 - count($nums));
        if ($last >= (1 << $lastBits)) {
            throw new SecurityException("Ambiguous numeric host '$host'");
        }
        $value = 0;
        foreach ($nums as $i => $n) {
            $value |= $n << (24 - 8 * $i);
        }
        $value |= $last;
        return pack('N', $value);
    }

    private static function isBlocked(string $packed): bool
    {
        if (strlen($packed) === 4) {
            return self::inRanges($packed, self::BLOCKED_V4);
        }
        // Embedded IPv4: mapped ::ffff:0:0/96, compatible ::/96, NAT64 64:ff9b::/96
        $prefix12 = substr($packed, 0, 12);
        if ($prefix12 === str_repeat("\0", 10) . "\xff\xff"
            || $prefix12 === str_repeat("\0", 12)
            || $prefix12 === "\x00\x64\xff\x9b" . str_repeat("\0", 8)
        ) {
            if ($prefix12 === str_repeat("\0", 12) && self::inRanges($packed, self::BLOCKED_V6)) {
                return true; // :: and ::1
            }
            return self::inRanges(substr($packed, 12, 4), self::BLOCKED_V4);
        }
        // 6to4 2002::/16 embeds IPv4 in bytes 2-5
        if (substr($packed, 0, 2) === "\x20\x02") {
            return self::inRanges(substr($packed, 2, 4), self::BLOCKED_V4);
        }
        return self::inRanges($packed, self::BLOCKED_V6);
    }

    /**
     * @param array<array{string,int}> $ranges 
     */
    private static function inRanges(string $packed, array $ranges): bool
    {
        foreach ($ranges as [$net, $bits]) {
            $netPacked = inet_pton($net);
            if ($netPacked === false || strlen($netPacked) !== strlen($packed)) {
                continue;
            }
            $bytes = intdiv($bits, 8);
            if (substr($packed, 0, $bytes) !== substr($netPacked, 0, $bytes)) {
                continue;
            }
            $rem = $bits % 8;
            if ($rem === 0) {
                return true;
            }
            $mask = (0xff << (8 - $rem)) & 0xff;
            if ((ord($packed[$bytes]) & $mask) === (ord($netPacked[$bytes]) & $mask)) {
                return true;
            }
        }
        return false;
    }
}
