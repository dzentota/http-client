<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\HttpClient\ResponseFactory;
use PHPUnit\Framework\TestCase;

class ResponseFactoryTest extends TestCase
{
    private ResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ResponseFactory();
    }

    public function testCreateResponseWithDefaults(): void
    {
        $response = $this->factory->createResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getReasonPhrase());
        $this->assertSame('1.1', $response->getProtocolVersion());
    }

    public function testCreateResponseWithCustomStatus(): void
    {
        $response = $this->factory->createResponse(404, 'Not Found');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
        $this->assertSame('1.1', $response->getProtocolVersion());
    }

    public function testCreateResponseWithOnlyStatusCode(): void
    {
        $response = $this->factory->createResponse(201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('', $response->getReasonPhrase());
    }
} 