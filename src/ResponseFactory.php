<?php

declare(strict_types=1);

namespace Dzentota\HttpClient;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new class($code, [], null, '1.1', $reasonPhrase) extends Response {
            private string $customReasonPhrase;
            
            public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', string $reason = '')
            {
                $this->customReasonPhrase = $reason;
                parent::__construct($status, $headers, $body, $version, $reason ?: null);
            }
            
            public function getReasonPhrase(): string
            {
                return $this->customReasonPhrase;
            }
            
            public function withStatus($code, $reasonPhrase = ''): ResponseInterface
            {
                $new = clone $this;
                $new->customReasonPhrase = (string) $reasonPhrase;
                $parent = parent::withStatus($code, $reasonPhrase ?: null);
                // Copy the custom reason phrase to the new instance
                if ($parent instanceof self) {
                    $parent->customReasonPhrase = (string) $reasonPhrase;
                }
                return $parent;
            }
        };
    }
} 