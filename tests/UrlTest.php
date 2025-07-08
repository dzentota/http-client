<?php

declare(strict_types=1);

namespace Dzentota\HttpClient\Tests;

use Dzentota\HttpClient\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testResolveWithEmptyBase(): void
    {
        $result = Url::resolve('https://example.com/path', '');
        $this->assertSame('https://example.com/path', $result);
    }

    public function testResolveWithEmptyUrl(): void
    {
        $result = Url::resolve('', 'https://example.com/base');
        $this->assertSame('https://example.com/base', $result);
    }

    public function testResolveAbsoluteUrl(): void
    {
        $result = Url::resolve('https://other.com/path', 'https://example.com/base');
        $this->assertSame('https://other.com/path', $result);
    }

    public function testResolveFragment(): void
    {
        $result = Url::resolve('#section', 'https://example.com/page');
        $this->assertSame('https://example.com/page#section', $result);
    }

    public function testResolveProtocolRelative(): void
    {
        $result = Url::resolve('//other.com/path', 'https://example.com/base');
        $this->assertSame('https://other.com/path', $result);
    }

    public function testResolveAbsolutePath(): void
    {
        $result = Url::resolve('/new-path', 'https://example.com/old/path');
        $this->assertSame('https://example.com/new-path', $result);
    }

    public function testResolveRelativePath(): void
    {
        $result = Url::resolve('new-path', 'https://example.com/old/path');
        $this->assertSame('https://example.com/old/new-path', $result);
    }

    public function testResolveCurrentDirectory(): void
    {
        $result = Url::resolve('./new-path', 'https://example.com/old/path');
        $this->assertSame('https://example.com/old/new-path', $result);
    }

    public function testResolveParentDirectory(): void
    {
        $result = Url::resolve('../new-path', 'https://example.com/old/current/path');
        $this->assertSame('https://example.com/old/new-path', $result);
    }

    public function testResolveMultipleParentDirectories(): void
    {
        $result = Url::resolve('../../new-path', 'https://example.com/a/b/c/d');
        $this->assertSame('https://example.com/a/new-path', $result);
    }

    public function testResolveDotEnding(): void
    {
        $result = Url::resolve('path/.', 'https://example.com/base/');
        $this->assertSame('https://example.com/base/path/', $result);
    }

    public function testResolveParentDotEnding(): void
    {
        $result = Url::resolve('path/..', 'https://example.com/base/current');
        $this->assertSame('https://example.com/base/', $result);
    }

    public function testBuildBasicUrl(): void
    {
        $parts = [
            'scheme' => 'https',
            'host' => 'example.com',
            'path' => '/path'
        ];
        
        $result = Url::build($parts);
        $this->assertSame('https://example.com/path', $result);
    }

    public function testBuildUrlWithIp(): void
    {
        $parts = [
            'scheme' => 'https',
            'host' => 'example.com',
            'path' => '/path'
        ];
        
        $result = Url::build($parts, '93.184.216.34');
        $this->assertSame('https://93.184.216.34/path', $result);
    }

    public function testBuildComplexUrl(): void
    {
        $parts = [
            'scheme' => 'https',
            'user' => 'username',
            'pass' => 'password',
            'host' => 'example.com',
            'port' => 8080,
            'path' => '/path',
            'query' => 'param=value',
            'fragment' => 'section'
        ];
        
        $result = Url::build($parts);
        $this->assertSame('https://username:password@example.com:8080/path?param=value#section', $result);
    }

    public function testBuildUrlWithOnlyUser(): void
    {
        $parts = [
            'scheme' => 'https',
            'user' => 'username',
            'host' => 'example.com',
            'path' => '/path'
        ];
        
        $result = Url::build($parts);
        $this->assertSame('https://username@example.com/path', $result);
    }

    public function testBuildUrlMinimal(): void
    {
        $parts = ['host' => 'example.com'];
        
        $result = Url::build($parts);
        $this->assertSame('example.com', $result);
    }
} 