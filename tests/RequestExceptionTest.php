<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\HttpClient\RequestException;
use PHPUnit\Framework\TestCase;

class RequestExceptionTest extends TestCase
{
    public function testExceptionImplementsClientExceptionInterface(): void
    {
        $exception = new RequestException('Test message');
        
        $this->assertInstanceOf(\Psr\Http\Client\ClientExceptionInterface::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'HTTP request failed';
        $exception = new RequestException($message);
        
        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionCode(): void
    {
        $code = 123;
        $exception = new RequestException('Test message', $code);
        
        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new RequestException('Test message', 0, $previous);
        
        $this->assertSame($previous, $exception->getPrevious());
    }
} 