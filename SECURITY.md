# Security Policy

## Overview

The `dzentota/http-client` library is designed with security as the primary concern. It implements multiple layers of protection against Server-Side Request Forgery (SSRF) attacks and follows the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) principles.

## Security Features

### 1. Secure DNS Resolution
- Uses secure DNS resolution via the `dzentota/dns-resolver` library
- Configurable DNS resolvers including native and DNS-over-HTTPS options
- Prevents DNS poisoning and manipulation attacks through secure resolution policies

### 2. SSRF Protection
- **DNS Rebinding Protection**: Resolves hostnames to IP addresses before making requests
- **Private Network Blocking**: Blocks requests to private IP ranges by default
- **IP Validation**: Validates resolved IPs against configurable blacklists
- **Redirect Protection**: Validates redirect targets for SSRF vulnerabilities

### 3. Default Blocked Networks

The library blocks the following private/internal IP ranges by default:

```
10.0.0.0/8       - RFC 1918 Private networks
172.16.0.0/12    - RFC 1918 Private networks  
192.168.0.0/16   - RFC 1918 Private networks
127.0.0.0/8      - RFC 3330 Loopback
169.254.0.0/16   - RFC 3330 Link local
224.0.0.0/4      - RFC 3330 Multicast
240.0.0.0/4      - RFC 3330 Reserved
```

### 4. SSL/TLS Security
- Enforces secure SSL/TLS configurations by default
- Validates peer certificates
- Disallows self-signed certificates by default
- Supports custom CA certificate bundles

### 5. Trusted Hosts
For legitimate internal services, the library supports trusted hosts that bypass SSRF protection:

```php
$client->trustTo('internal-api.company.com', 'admin.company.com');
```

**Warning**: Use trusted hosts sparingly and only for known, controlled internal services.

## Security Best Practices

### 1. Input Validation
Always validate URLs before passing them to the HTTP client:

```php
function isValidExternalUrl(string $url): bool {
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Only allow HTTP/HTTPS
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    
    // Additional validation logic...
    return true;
}
```

### 2. Allowlist Known Domains
For user-provided URLs, consider implementing an allowlist of known safe domains:

```php
$allowedDomains = ['api.partner.com', 'webhook.service.com'];
$host = parse_url($url, PHP_URL_HOST);

if (!in_array($host, $allowedDomains, true)) {
    throw new InvalidArgumentException('Domain not allowed');
}
```

### 3. Monitor and Log
Implement logging for security-related events:

```php
try {
    $response = $client->get($url);
} catch (InvalidArgumentException $e) {
    // Log potential SSRF attempt
    error_log("SSRF attempt blocked: {$url} - {$e->getMessage()}");
    throw $e;
}
```

### 4. Keep Dependencies Updated
Regularly update the DNS resolver and other dependencies:

```bash
composer update dzentota/dns-resolver
```

### 5. Custom Private IP Ranges
Configure additional private IP ranges if needed for your environment:

```php
$client->setPrivateIpRanges([
    '10.0.0.0|10.255.255.255',
    '172.16.0.0|172.31.255.255',
    '192.168.0.0|192.168.255.255',
    '127.0.0.0|127.255.255.255',
    // Add your custom ranges
    '203.0.113.0|203.0.113.255'  // Test range
]);
```

## Reporting Security Vulnerabilities

If you discover a security vulnerability in this library, please report it responsibly:

1. **Do not** create a public GitHub issue
2. Send details to the maintainer via private communication
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if available)

## Security Headers

The library automatically sets secure defaults for HTTP headers:

- Properly validates `Host` headers to prevent header injection
- Uses secure SSL/TLS configurations
- Implements proper redirect validation

## Testing Security

The library includes security-focused tests:

```bash
# Run security-related tests
composer test -- --group security

# Run static analysis for security issues
composer phpstan
```

## Compliance

This library is designed to comply with:

- **OWASP Top 10** - Addresses SSRF vulnerabilities (A10:2021)
- **AppSec Manifesto** - Follows secure-by-default principles
- **RFC 3986** - Proper URL parsing and validation
- **RFC 7230-7235** - HTTP/1.1 specification compliance

## Known Limitations

1. **File Protocol**: The library blocks `file://` URLs by design
2. **Custom Schemes**: Only `http://` and `https://` are supported
3. **IPv6**: Limited IPv6 support in private IP validation
4. **Proxy Support**: Basic proxy support without advanced proxy chain validation

## Security Changelog

- **v1.0.0**: Initial release with comprehensive SSRF protection
- Future versions will include additional security enhancements

## References

- [OWASP SSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)
- [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto)
- [RFC 1918 - Private Networks](https://tools.ietf.org/html/rfc1918)
- [RFC 3330 - Special-Use IPv4 Addresses](https://tools.ietf.org/html/rfc3330) 