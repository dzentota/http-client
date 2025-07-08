<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\HttpClient\RequestFactory;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{
    private RequestFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new RequestFactory();
    }

    public function testCreateRequest(): void
    {
        $request = $this->factory->createRequest('GET', 'https://example.com');

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://example.com', (string)$request->getUri());
    }

    public function testCreateJsonRequestWithoutBody(): void
    {
        $request = $this->factory->createJsonRequest('POST', 'https://example.com/api');

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://example.com/api', (string)$request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEmpty((string)$request->getBody());
    }

    public function testCreateJsonRequestWithArrayBody(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $request = $this->factory->createJsonRequest('POST', 'https://example.com/api', $data);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://example.com/api', (string)$request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('{"name":"John","email":"john@example.com"}', (string)$request->getBody());
    }

    public function testCreateJsonRequestWithStringBody(): void
    {
        $data = 'test string';
        $request = $this->factory->createJsonRequest('POST', 'https://example.com/api', $data);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://example.com/api', (string)$request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('"test string"', (string)$request->getBody());
    }

    public function testCreateJsonRequestWithInvalidJsonThrowsException(): void
    {
        // Create a resource that can't be JSON encoded
        $resource = fopen('php://memory', 'r');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to encode the body');

        try {
            $this->factory->createJsonRequest('POST', 'https://example.com/api', $resource);
        } finally {
            fclose($resource);
        }
    }
} 