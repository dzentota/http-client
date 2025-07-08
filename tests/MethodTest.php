<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\HttpClient\Method;
use PHPUnit\Framework\TestCase;

class MethodTest extends TestCase
{
    public function testMethodCases(): void
    {
        $this->assertSame('GET', Method::GET->value);
        $this->assertSame('POST', Method::POST->value);
        $this->assertSame('PUT', Method::PUT->value);
        $this->assertSame('PATCH', Method::PATCH->value);
        $this->assertSame('DELETE', Method::DELETE->value);
        $this->assertSame('HEAD', Method::HEAD->value);
        $this->assertSame('OPTIONS', Method::OPTIONS->value);
        $this->assertSame('TRACE', Method::TRACE->value);
        $this->assertSame('CONNECT', Method::CONNECT->value);
    }

    public function testAllMethodsAreValidEnums(): void
    {
        $methods = Method::cases();

        foreach ($methods as $method) {
            $this->assertInstanceOf(Method::class, $method);
            $this->assertIsString($method->value);
            $this->assertSame(strtoupper($method->value), $method->value, "Method {$method->name} should be uppercase");
        }
    }

    public function testEnumHasAllExpectedMethods(): void
    {
        $expectedMethods = [
            'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 
            'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'
        ];

        $actualMethods = array_map(fn(Method $method) => $method->value, Method::cases());

        foreach ($expectedMethods as $expected) {
            $this->assertContains($expected, $actualMethods, "Method enum should contain {$expected}");
        }

        $this->assertCount(count($expectedMethods), $actualMethods, "Method enum should have exactly " . count($expectedMethods) . " cases");
    }
} 