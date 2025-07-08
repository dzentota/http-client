<?php

declare(strict_types=1);

namespace Dzentota\HttpClient;

use Psr\Http\Client\ClientExceptionInterface;

class RequestException extends \RuntimeException implements ClientExceptionInterface
{
} 