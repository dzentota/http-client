# dzentota/http-client

A secure HTTP client library that prevents SSRF (Server-Side Request Forgery) attacks using secure DNS resolution. This library is designed following AppSec best practices to ensure safe external HTTP requests.

## Features

- **SSRF Protection**: Automatic protection against Server-Side Request Forgery attacks
- **Secure DNS Resolution**: Uses secure DNS resolution via [dzentota/dns-resolver](https://github.com/dzentota/dns-resolver) with configurable resolvers
- **DNS Rebinding Protection**: Prevents DNS rebinding attacks by resolving hostnames to IP addresses
- **Private Network Blocking**: Blocks requests to private IP ranges by default
- **PSR-18 HTTP Client**: Fully compliant with PSR-18 HTTP Client interface
- **PSR-7 Messages**: Uses PSR-7 compatible request and response objects
- **Trusted Hosts**: Support for whitelisting trusted domains
- **SSL/TLS Security**: Enforces secure SSL/TLS configurations
- **Retry Logic**: Built-in exponential backoff retry mechanism
- **Redirect Handling**: Safe redirect following with SSRF protection

## Installation

```bash
composer require dzentota/http-client
```

## Requirements

- PHP 8.1 or higher
- dzentota/dns-resolver ^1.0

## Quick Start

```php
<?php

use Dzentota\HttpClient\HttpClient;
use Dzentota\DnsResolver\Resolver;

// Basic usage
$client = HttpClient::create();
$response = $client->get('https://api.example.com/data');

echo $response->getStatusCode(); // 200
echo $response->getBody(); // Response content

// Using custom DNS resolver (default is SecureNativeResolver)
$resolver = new Resolver(); // or other resolver implementations
$client = HttpClient::create();
$client->setResolver($resolver);

$response = $client->post('https://api.example.com/data', [
    'key' => 'value'
]);
```

## Security Features

### SSRF Protection

The library automatically prevents SSRF attacks by:

1. **DNS Resolution**: Resolving hostnames to IP addresses using secure DNS resolution
2. **Private IP Blocking**: Blocking requests to private/internal IP ranges
3. **IP Validation**: Validating resolved IPs against blacklisted ranges
4. **Redirect Protection**: Validating redirect targets for SSRF vulnerabilities

### Default Blocked IP Ranges

- `10.0.0.0/8` - RFC 1918 Private networks
- `172.16.0.0/12` - RFC 1918 Private networks  
- `192.168.0.0/16` - RFC 1918 Private networks
- `127.0.0.0/8` - RFC 3330 Loopback
- `169.254.0.0/16` - RFC 3330 Link local
- `224.0.0.0/4` - RFC 3330 Multicast
- `240.0.0.0/4` - RFC 3330 Reserved

### Trusted Hosts

For internal services that you need to access, use trusted hosts:

```php
$client = HttpClient::create();
$client->trustTo('internal-api.company.com', 'admin.company.com');

// These requests will bypass IP resolution
$response = $client->get('https://internal-api.company.com/data');
```

## Configuration

### Factory Method

```php
$client = HttpClient::create([
    'timeout' => 30.0,
    'maxRedirects' => 5,
    'userAgent' => 'MyApp/1.0',
    'resolver' => $customResolver,
    'trustedHosts' => ['api.trusted-partner.com']
]);
```

### Method Chaining

```php
$client = HttpClient::create()
    ->setTimeout(15.0)
    ->setMaxRedirects(3)
    ->setMaxRetries(2)
    ->trustTo('api.partner.com')
    ->setResolver($dnsResolver)
    ->setSslContextOptions([
        'verify_peer' => true,
        'verify_peer_name' => true,
        'cafile' => '/path/to/ca-bundle.crt'
    ]);
```

## HTTP Methods

### GET Request

```php
$response = $client->get('https://api.example.com/users', [
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json'
]);
```

### POST Request

```php
// Form data
$response = $client->post('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// JSON data
$response = $client->post('https://api.example.com/users', 
    json_encode(['name' => 'John Doe']), 
    ['Content-Type' => 'application/json']
);
```

### PUT Request

```php
$response = $client->put('https://api.example.com/users/123', [
    'name' => 'John Smith',
    'email' => 'john.smith@example.com'
]);
```

### DELETE Request

```php
$response = $client->delete('https://api.example.com/users/123');
```

### HEAD Request

```php
$response = $client->head('https://api.example.com/users/123');
echo $response->getHeaderLine('Content-Length');
```

### PATCH Request

```php
$response = $client->patch('https://api.example.com/users/123', [
    'email' => 'newemail@example.com'
]);
```

## PSR-7 Request/Response

The library is fully compatible with PSR-7:

```php
use GuzzleHttp\Psr7\Request;

$request = new Request('GET', 'https://api.example.com/data', [
    'Authorization' => 'Bearer ' . $token
]);

$response = $client->sendRequest($request);
```

## Advanced Configuration

### DNS Resolver Configuration

By default, the client uses `SecureNativeResolver` with strict security policies. You can configure a different resolver if needed:

```php
use Dzentota\DnsResolver\SecureNativeResolver;
use Dzentota\DnsResolver\SecurityPolicy;

// Default behavior (SecureNativeResolver with strict policy)
$client = HttpClient::create();

// Custom resolver configuration
$resolver = new SecureNativeResolver(SecurityPolicy::createStrict());
$client = HttpClient::create(['resolver' => $resolver]);

// For DNS-over-HTTPS, check the dzentota/dns-resolver package documentation
// for available DoH resolver implementations
```

### SSL/TLS Configuration

```php
$client->setSslContextOptions([
    'verify_peer' => true,
    'verify_peer_name' => true,
    'allow_self_signed' => false,
    'cafile' => '/path/to/ca-bundle.crt',
    'ciphers' => 'ECDHE+AESGCM:ECDHE+CHACHA20:DHE+AESGCM:DHE+CHACHA20:!aNULL:!MD5:!DSS'
]);
```

### Socket Options

```php
$client->setSocketContextOptions([
    'bindto' => '192.168.1.100:0'
]);
```

### Retry Configuration

```php
$client->setMaxRetries(3); // Retry up to 3 times on 5xx errors
```

### Redirect Handling

```php
// Disable redirects
$client->setMaxRedirects(0);

// Enable strict RFC compliance (POST redirects remain POST)
$client->useStrictRedirects();
```

## Error Handling

```php
use Dzentota\HttpClient\RequestException;

try {
    $response = $client->get('https://api.example.com/data');
} catch (RequestException $e) {
    echo "HTTP request failed: " . $e->getMessage();
} catch (\InvalidArgumentException $e) {
    echo "Invalid configuration: " . $e->getMessage();
}
```

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer phpstan
```

Check code style:

```bash
composer cs-check
```

Fix code style:

```bash
composer cs-fix
```

## Security Considerations

1. **Always validate URLs** before passing them to the client
2. **Use trusted hosts sparingly** and only for known internal services
3. **Keep DNS resolver updated** to ensure latest security patches
4. **Monitor for bypass attempts** in your application logs
5. **Consider additional validation** for user-provided URLs

## AppSec Manifesto Compliance

This library follows the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) principles:

- **Secure by Default**: SSRF protection is enabled by default
- **Defense in Depth**: Multiple layers of protection (DNS, IP validation, redirect protection)
- **Fail Securely**: Blocks suspicious requests rather than allowing them
- **Principle of Least Privilege**: Only allows necessary network access
- **Input Validation**: Validates all URLs and configuration parameters

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Related Projects

- [dzentota/dns-resolver](https://github.com/dzentota/dns-resolver) - Secure DNS resolver library with configurable resolvers
- [dzentota/AppSecManifesto](https://github.com/dzentota/AppSecManifesto) - Application Security principles and guidelines 