<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dzentota\HttpClient\HttpClient;
use Dzentota\DnsResolver\SecureNativeResolver;
use Dzentota\DnsResolver\SecurityPolicy;

// Basic usage example
echo "=== dzentota/http-client Usage Examples ===\n\n";

// Example 1: Basic GET request
echo "1. Basic GET Request:\n";
$client = HttpClient::create();

try {
    $response = $client->get('https://httpbin.org/json');
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content-Type: " . $response->getHeaderLine('content-type') . "\n";
    echo "Body length: " . strlen($response->getBody()) . " bytes\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 2: POST request with JSON data
echo "2. POST Request with JSON:\n";
try {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
    
    $response = $client->post(
        'https://httpbin.org/post',
        json_encode($data),
        ['Content-Type' => 'application/json']
    );
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Response received successfully\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 3: Using custom DNS resolver with strict security policy
echo "3. Using Custom DNS Resolver with Strict Security:\n";
try {
    $resolver = new SecureNativeResolver(SecurityPolicy::createStrict());
    $client = HttpClient::create([
        'resolver' => $resolver,
        'timeout' => 15.0,
        'maxRedirects' => 3,
        'userAgent' => 'dzentota-http-client-example/1.0'
    ]);
    
    $response = $client->get('https://httpbin.org/user-agent');
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Custom user agent sent successfully with secure DNS resolution\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 4: Trusted hosts (for internal APIs)
echo "4. Trusted Hosts Configuration:\n";
try {
    $client = HttpClient::create([
        'timeout' => 10.0,
        'trustedHosts' => ['internal-api.company.com', 'admin.company.com'],
        'userAgent' => 'MyApp/1.0'
    ]);
    
    echo "Client configured with trusted hosts\n";
    echo "Trusted hosts bypass DNS resolution and SSRF protection\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 5: SSRF Protection demonstration
echo "5. SSRF Protection (this will fail):\n";
try {
    $client = HttpClient::create();
    // This should fail due to SSRF protection from the DNS resolver
    $response = $client->get('http://localhost:8080/admin');
    echo "This should not be reached\n";
} catch (Exception $e) {
    echo "Expected error (SSRF protection): " . $e->getMessage() . "\n\n";
}

// Example 6: Different HTTP methods
echo "6. Different HTTP Methods:\n";
try {
    $client = HttpClient::create();
    
    // HEAD request
    $response = $client->head('https://httpbin.org/get');
    echo "HEAD - Status: " . $response->getStatusCode() . "\n";
    
    // PUT request
    $response = $client->put('https://httpbin.org/put', ['data' => 'test']);
    echo "PUT - Status: " . $response->getStatusCode() . "\n";
    
    // DELETE request
    $response = $client->delete('https://httpbin.org/delete');
    echo "DELETE - Status: " . $response->getStatusCode() . "\n";
    
    // PATCH request
    $response = $client->patch('https://httpbin.org/patch', ['field' => 'value']);
    echo "PATCH - Status: " . $response->getStatusCode() . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 7: Builder pattern configuration
echo "7. Builder Pattern Configuration:\n";
try {
    $client = HttpClient::create()
        ->setTimeout(30.0)
        ->setMaxRedirects(5)
        ->setUserAgent('MySecureApp/2.0')
        ->trustTo('trusted.internal.com', 'api.internal.com')
        ->useStrictRedirects();
    
    echo "Client configured with builder pattern\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

echo "=== Examples completed ===\n"; 