<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\DnsResolver\Resolver;
use Dzentota\HttpClient\HttpClient;
use Dzentota\HttpClient\RequestException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class HttpClientTest extends TestCase
{
    private HttpClient $client;
    private Resolver&MockObject $mockResolver;

    protected function setUp(): void
    {
        $this->client = HttpClient::create();
        $this->mockResolver = $this->createMock(Resolver::class);
    }

    public function testConstructorSetsDefaults(): void
    {
        $client = HttpClient::create();
        
        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame('dzentota-http-client/1.0', $client->getUserAgent());
    }

    public function testConstructorWithCustomValues(): void
    {
        $client = HttpClient::create([
            'timeout' => 15.0,
            'maxRedirects' => 5
        ]);
        
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function testSetResolver(): void
    {
        $result = $this->client->setResolver($this->mockResolver);
        
        $this->assertSame($this->client, $result);
    }

    public function testTrustToWithValidHostnames(): void
    {
        $result = $this->client->trustTo('example.com', 'api.example.com');
        
        $this->assertSame($this->client, $result);
    }

    public function testTrustToWithInvalidHostname(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hostname invalid..hostname');
        
        $this->client->trustTo('invalid..hostname');
    }

    public function testSetSocketContextOptions(): void
    {
        $options = ['bindto' => '0.0.0.0:0'];
        $result = $this->client->setSocketContextOptions($options);
        
        $this->assertSame($this->client, $result);
    }

    public function testSetSslContextOptions(): void
    {
        $options = ['cafile' => '/path/to/ca.pem'];
        $result = $this->client->setSslContextOptions($options);
        
        $this->assertSame($this->client, $result);
    }

    public function testUseStrictRedirects(): void
    {
        $result = $this->client->useStrictRedirects();
        
        $this->assertSame($this->client, $result);
    }

    public function testSetTimeout(): void
    {
        $result = $this->client->setTimeout(20.0);
        
        $this->assertSame($this->client, $result);
    }

    public function testSetTimeoutWithZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Timeout can't be 0");
        
        $this->client->setTimeout(0);
    }

    public function testSetTimeoutWithZeroFloat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Timeout can't be 0");
        
        $this->client->setTimeout(0.0);
    }

    public function testSetMaxRedirects(): void
    {
        $result = $this->client->setMaxRedirects(5);
        
        $this->assertSame($this->client, $result);
    }

    public function testSetMaxRedirectsWithNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The max number of redirects can not be less than zero');
        
        $this->client->setMaxRedirects(-1);
    }

    public function testSetMaxRetries(): void
    {
        $result = $this->client->setMaxRetries(3);
        
        $this->assertSame($this->client, $result);
    }

    public function testSetMaxRetriesWithNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The max number of retries cannot be less than zero');
        
        $this->client->setMaxRetries(-1);
    }

    public function testSetBaseUri(): void
    {
        $result = $this->client->setBaseUri('https://api.example.com');
        
        $this->assertSame($this->client, $result);
    }

    public function testSetAndGetUserAgent(): void
    {
        $userAgent = 'MyApp/1.0';
        $this->client->setUserAgent($userAgent);
        
        $this->assertSame($userAgent, $this->client->getUserAgent());
    }

    public function testGetUserAgentDefault(): void
    {
        $this->assertSame('dzentota-http-client/1.0', $this->client->getUserAgent());
    }

    public function testDefaultUserAgent(): void
    {
        $this->assertSame('dzentota-http-client/1.0', HttpClient::defaultUserAgent());
    }

    public function testCreateWithDefaults(): void
    {
        $client = HttpClient::create();
        
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function testCreateWithConfig(): void
    {
        $config = [
            'timeout' => 30.0,
            'maxRedirects' => 5,
            'userAgent' => 'TestAgent/1.0',
            'resolver' => $this->mockResolver,
            'trustedHosts' => ['trusted.example.com']
        ];
        
        $client = HttpClient::create($config);
        
        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame('TestAgent/1.0', $client->getUserAgent());
    }

    public function testHttpMethodShortcuts(): void
    {
        // This test would require actual HTTP calls or complex mocking
        // For now, just test that the methods exist and are callable
        $this->assertTrue(method_exists($this->client, 'get'));
        $this->assertTrue(method_exists($this->client, 'post'));
        $this->assertTrue(method_exists($this->client, 'put'));
        $this->assertTrue(method_exists($this->client, 'patch'));
        $this->assertTrue(method_exists($this->client, 'delete'));
        $this->assertTrue(method_exists($this->client, 'head'));
    }

    public function testGetResponseFactory(): void
    {
        $responseFactory = $this->client->getResponseFactory();
        
        $this->assertInstanceOf(\Psr\Http\Message\ResponseFactoryInterface::class, $responseFactory);
    }

    public function testSetResponseFactory(): void
    {
        $mockResponseFactory = $this->createMock(\Psr\Http\Message\ResponseFactoryInterface::class);
        $result = $this->client->setResponseFactory($mockResponseFactory);
        
        $this->assertSame($this->client, $result);
    }
} 