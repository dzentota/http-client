<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\DnsResolver\Resolver;
use Dzentota\DnsResolver\SecurityException;
use Dzentota\HttpClient\HttpClient;
use Dzentota\HttpClient\IpLiteralGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * IP-literal hosts must be checked against the private/reserved blocklist
 * before any connection attempt (they bypass DNS resolution).
 */
class IpLiteralSsrfTest extends TestCase
{
    public static function blockedUrls(): array
    {
        $hosts = [
            'loopback' => '127.0.0.1',
            'loopback other' => '127.5.6.7',
            'metadata' => '169.254.169.254',
            'rfc1918 10' => '10.0.0.5',
            'rfc1918 172' => '172.16.0.1',
            'rfc1918 192' => '192.168.1.1',
            'zero' => '0.0.0.0',
            'cgnat' => '100.64.0.1',
            'broadcast' => '255.255.255.255',
            'multicast v4' => '224.0.0.1',
            'decimal' => '2130706433',
            'decimal metadata' => '2852039166',
            'octal' => '0177.0.0.1',
            'octal full' => '017700000001',
            'hex short' => '0x7f.1',
            'hex full' => '0x7f000001',
            'hex dotted' => '0x7f.0x0.0x0.0x1',
            'short' => '127.1',
            'short 3 parts' => '10.0.5',
            'trailing dot' => '127.0.0.1.',
            'v6 loopback' => '[::1]',
            'v6 unspecified' => '[::]',
            'v6 ula' => '[fc00::1]',
            'v6 ula fd' => '[fd12:3456::1]',
            'v6 link local' => '[fe80::1]',
            'v6 link local zone' => '[fe80::1%25en0]',
            'v6 multicast' => '[ff02::1]',
            'v4 mapped' => '[::ffff:127.0.0.1]',
            'v4 mapped hex' => '[::ffff:7f00:1]',
            'v4 mapped metadata' => '[::ffff:169.254.169.254]',
            'v4 compatible' => '[::127.0.0.1]',
            'nat64' => '[64:ff9b::7f00:1]',
            'nat64 metadata' => '[64:ff9b::a9fe:a9fe]',
            '6to4' => '[2002:7f00:1::]',
            '6to4 private' => '[2002:0a00:0005::1]',
            'teredo' => '[2001:0::1]',
            'documentation' => '[2001:db8::1]',
        ];
        $out = [];
        foreach ($hosts as $name => $host) {
            $out[$name] = ["https://$host/latest/meta-data/"];
        }
        $out['with port'] = ['http://127.0.0.1:8080/'];
        $out['with userinfo'] = ['http://user:pass@127.0.0.1/'];
        return $out;
    }

    #[DataProvider('blockedUrls')]
    public function testIpLiteralBlockedBeforeConnecting(string $url): void
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->expects($this->never())->method('resolveToIp');

        $client = HttpClient::create()->setResolver($resolver)->setTimeout(5)->setMaxRetries(0);

        $start = microtime(true);
        try {
            $client->get($url);
            $this->fail("Request to $url was not blocked");
        } catch (SecurityException $e) {
            $this->assertLessThan(0.5, microtime(true) - $start, 'Check must run before any connect attempt');
        }
    }

    public static function publicHosts(): array
    {
        return [
            ['8.8.8.8'], ['1.1.1.1'], ['93.184.216.34'], ['[2606:4700:4700::1111]'],
            ['[2001:4860:4860::8888]'], ['[::ffff:8.8.8.8]'], ['[64:ff9b::808:808]'], ['[2002:808:808::1]'],
        ];
    }

    #[DataProvider('publicHosts')]
    public function testPublicIpLiteralsPassGuard(string $host): void
    {
        $this->assertNull(IpLiteralGuard::assertSafe($host));
    }

    public function testHostnamesAreLeftToResolver(): void
    {
        $this->assertNull(IpLiteralGuard::assertSafe('example.com'));
        $this->assertNull(IpLiteralGuard::assertSafe('1.example.com'));
        $this->assertNull(IpLiteralGuard::assertSafe('0x7f.example'));
    }

    public function testRedirectToIpLiteralIsBlocked(): void
    {
        // Serve a redirect from a local server via a trusted host so the first hop is allowed.
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            $this->markTestSkipped("Cannot bind local socket: $errstr");
        }
        $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
        $pid = function_exists('pcntl_fork') ? pcntl_fork() : -1;
        if ($pid === -1) {
            $this->markTestSkipped('pcntl not available');
        }
        if ($pid === 0) {
            $conn = @stream_socket_accept($sock, 5);
            if ($conn) {
                fread($conn, 8192);
                fwrite($conn, "HTTP/1.1 302 Found\r\nLocation: http://0x7f.1:$port/secret\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                fclose($conn);
            }
            // A second connection here would mean the redirect was followed.
            $second = @stream_socket_accept($sock, 1);
            exit($second ? 1 : 0);
        }
        fclose($sock);

        $client = HttpClient::create()->setTimeout(3)->trustTo('localhost');
        try {
            $client->get("http://localhost:$port/");
            $this->fail('Redirect to IP literal was followed');
        } catch (SecurityException $e) {
            $this->assertStringContainsString('0x7f.1', $e->getMessage());
        } finally {
            pcntl_waitpid($pid, $status);
        }
        $this->assertSame(0, pcntl_wexitstatus($status), 'Server saw a second connection');
    }

    public function testTrustedIpLiteralSkipsGuard(): void
    {
        // Explicit opt-in: trusted host passes the guard and a connection is attempted.
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            $this->markTestSkipped("Cannot bind local socket: $errstr");
        }
        $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
        $pid = function_exists('pcntl_fork') ? pcntl_fork() : -1;
        if ($pid === -1) {
            $this->markTestSkipped('pcntl not available');
        }
        if ($pid === 0) {
            $conn = @stream_socket_accept($sock, 5);
            if ($conn) {
                fread($conn, 8192);
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok");
                fclose($conn);
            }
            exit(0);
        }
        fclose($sock);
        $client = HttpClient::create()->setTimeout(3)->trustTo('127.0.0.1');
        $response = $client->get("http://127.0.0.1:$port/");
        pcntl_waitpid($pid, $status);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }
}
