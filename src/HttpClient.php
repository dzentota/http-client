<?php

declare(strict_types=1);

namespace Dzentota\HttpClient;

use Dzentota\DnsResolver\Resolver;
use Dzentota\DnsResolver\SecureNativeResolver;
use Dzentota\DnsResolver\SecurityPolicy;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class HttpClient implements ClientInterface
{
    public const CLIENT_VERSION = '1.0';

    // Configuration properties
    private float $timeout;
    private int $maxRedirects;
    private int $maxRetries;
    private bool $strictRedirects = false;
    private ?string $userAgent = null;
    private string $baseUri = '';

    // Dependencies
    private ?Resolver $resolver = null;
    private ResponseFactoryInterface $responseFactory;

    // Context options
    private array $socketContextOptions = [];
    private array $sslContextOptions = [];

    // Runtime state
    private int $redirectsCount = 0;
    private int $retryCount = 0;
    private array $trustedHosts = [];

    /**
     * Private constructor - use create() factory method instead
     */
    private function __construct()
    {
        $this->setTimeout(10.0);
        $this->setMaxRedirects(3);
        $this->setMaxRetries(0);
        $this->setUserAgent(self::defaultUserAgent());
    }

    /**
     * Sets custom DNS resolver
     */
    public function setResolver(Resolver $resolver): self
    {
        $this->resolver = $resolver;
        return $this;
    }

    /**
     * Adds hosts to the list of trusted hostnames
     */
    public function trustTo(string ...$hosts): self
    {
        foreach ($hosts as $host) {
            if (false === filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                throw new \InvalidArgumentException("Invalid hostname $host");
            }
            $this->trustedHosts[] = $host;
        }
        return $this;
    }

    /**
     * Set socket context options
     * @see https://www.php.net/manual/en/context.socket.php
     */
    public function setSocketContextOptions(array $options): self
    {
        $this->socketContextOptions = $options;
        return $this;
    }

    /**
     * Set SSL context options with secure defaults
     * @see https://www.php.net/manual/en/context.ssl.php
     */
    public function setSslContextOptions(array $options): self
    {
        // Enforce safe defaults
        $this->sslContextOptions = array_merge($options, [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]);
        return $this;
    }

    /**
     * Force RFC compliance for redirects
     * Strict RFC compliant redirects mean that POST redirect requests are sent as POST requests vs
     * doing what most browsers do which is redirect POST requests with GET requests
     */
    public function useStrictRedirects(): self
    {
        $this->strictRedirects = true;
        return $this;
    }

    /**
     * Set read timeout in seconds
     * Specifying a negative value means an infinite timeout.
     */
    public function setTimeout(float $timeout): self
    {
        if ($timeout == 0) {
            throw new \InvalidArgumentException("Timeout can't be 0. Specifying a negative value means an infinite timeout.");
        }
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set the max number of redirects to follow
     * Value 0 means that no redirects are followed
     */
    public function setMaxRedirects(int $maxRedirects): self
    {
        if ($maxRedirects < 0) {
            throw new \InvalidArgumentException('The max number of redirects can not be less than zero');
        }
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * Set the maximum number of retries for a failed request
     * Uses exponential backoff algorithm
     * @see https://cloud.google.com/iot/docs/how-tos/exponential-backoff#example_algorithm
     */
    public function setMaxRetries(int $maxRetries): self
    {
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('The max number of retries cannot be less than zero');
        }
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * Set base URI for relative URLs
     */
    public function setBaseUri(string $baseUri): self
    {
        $this->baseUri = rtrim($baseUri, '/');
        return $this;
    }

    /**
     * Set custom response factory
     */
    public function setResponseFactory(ResponseFactoryInterface $responseFactory): self
    {
        $this->responseFactory = $responseFactory;
        return $this;
    }

    /**
     * Get configured response factory
     */
    public function getResponseFactory(): ResponseFactoryInterface
    {
        if (!isset($this->responseFactory)) {
            $this->responseFactory = new ResponseFactory();
        }
        return $this->responseFactory;
    }

    /**
     * Set custom user agent
     */
    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Get current user agent
     */
    public function getUserAgent(): string
    {
        return $this->userAgent ?? self::defaultUserAgent();
    }

    /**
     * Get default user agent string
     */
    public static function defaultUserAgent(): string
    {
        return sprintf('dzentota-http-client/%s', self::CLIENT_VERSION);
    }

    /**
     * Perform GET request
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        return $this->request(Method::GET, $url, null, $headers);
    }

    /**
     * Perform POST request
     */
    public function post(string $url, $body, array $headers = []): ResponseInterface
    {
        return $this->request(Method::POST, $url, is_string($body) ? $body : http_build_query((array)$body), array_merge(['Content-type' => 'application/x-www-form-urlencoded'], $headers));
    }

    /**
     * Perform PUT request
     */
    public function put(string $url, $body, array $headers = []): ResponseInterface
    {
        return $this->request(Method::PUT, $url, is_string($body) ? $body : http_build_query((array)$body), array_merge(['Content-type' => 'application/x-www-form-urlencoded'], $headers));
    }

    /**
     * Perform PATCH request
     */
    public function patch(string $url, $body, array $headers = []): ResponseInterface
    {
        return $this->request(Method::PATCH, $url, is_string($body) ? $body : http_build_query((array)$body), array_merge(['Content-type' => 'application/x-www-form-urlencoded'], $headers));
    }

    /**
     * Perform DELETE request
     */
    public function delete(string $url, array $headers = []): ResponseInterface
    {
        return $this->request(Method::DELETE, $url, null, $headers);
    }

    /**
     * Perform HEAD request
     */
    public function head(string $url, array $headers = []): ResponseInterface
    {
        return $this->request(Method::HEAD, $url, null, $headers);
    }

    /**
     * PSR-18 ClientInterface implementation
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $method = Method::from($request->getMethod());
        $url = (string) $request->getUri();
        $body = $request->getBody()->getContents();
        
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $this->request($method, $url, $body ?: null, $headers);
    }

    /**
     * Send HTTP request
     */
    public function request(Method $method, string $url, ?string $body = null, array $headers = []): ResponseInterface
    {
        $methodString = $method->value;
        
        if (!empty($this->baseUri) && !str_starts_with($url, 'http')) {
            $url = $this->baseUri . '/' . ltrim($url, '/');
        }

        // Parse and validate URL
        $this->validateUrl($url);
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!is_string($host)) {
            throw new \InvalidArgumentException('Invalid url: missing or invalid host.');
        }
        
        // Determine target URL for the request
        $targetUrl = $this->resolveTargetUrl($url, $host);
        
        $requestHeaders = $this->buildHeaders($headers, $host);
        
        $context = stream_context_create([
            'http' => array_merge([
                'method' => $methodString,
                'header' => implode("\r\n", $requestHeaders),
                'content' => $body,
                'timeout' => $this->timeout,
                'follow_location' => 0, // We handle redirects manually
                'ignore_errors' => true, // We handle HTTP errors manually
                'user_agent' => $this->getUserAgent(),
            ], $this->socketContextOptions),
            'ssl' => array_merge($this->sslContextOptions, [
                'peer_name' => $host, // Use original hostname for SSL verification
            ]),
        ]);

        $retryCount = 0;
        $content = false;
        $http_response_header = [];
        
        while ($retryCount <= $this->maxRetries) {
            $content = @file_get_contents($targetUrl, false, $context);
            
            if ($content !== false) {
                break;
            }
            
            if ($retryCount < $this->maxRetries) {
                $retryCount++;
                $delay = $this->getExponentialBackoffDelayInMicroseconds($retryCount);
                usleep($delay);
            } else {
                $exception = new RequestException("Failed to fetch '$url' after {$this->maxRetries} retries");
                $this->retryCount = 0;
                throw $exception;
            }
        }

        $this->retryCount = 0;

        if ($content === false) {
            throw new RequestException("Failed to fetch '$url'");
        }

        // Follow redirects.
        if ($this->maxRedirects > 0 && $this->redirectsCount < $this->maxRedirects) {
            $statusCode = $this->getStatusCode($http_response_header);
            
            foreach ($http_response_header as $header) {
                $canonicalHeader = strtolower(trim($header));
                if (str_starts_with($canonicalHeader, 'location: ')) {
                    $this->redirectsCount++;
                    $redirectUrl = $this->resolveRedirectUrl(substr(trim($header), 10), $url);
                    if ($statusCode === 303 ||
                        ($statusCode <= 302 && !$this->strictRedirects)
                    ) {
                        $safeMethods = [Method::GET, Method::HEAD, Method::OPTIONS];
                        $method = in_array($method, $safeMethods, true) ? $method : Method::GET;
                        $body = null;
                    }
                    try {
                        return $this->request($method, $redirectUrl, $body, $headers);
                    } catch (RequestException $exception) {
                        $this->redirectsCount = 0;
                        throw $exception;
                    }
                }
            }
        }
        $this->redirectsCount = 0;

        return $this->createResponse($http_response_header, $content);
    }

    /**
     * Creates PSR-7 Response using configured response factory
     * @param array $responseHeaders
     * @param string $content
     * @return ResponseInterface
     */
    private function createResponse(array $responseHeaders, string $content): ResponseInterface
    {
        $headers = $this->normalizeResponseHeaders($responseHeaders);
        $statusLine = array_shift($headers);
        [$code, $reasonPhrase] = $this->getHttpStatus($statusLine);
        $response = $this->getResponseFactory()->createResponse();
        $response = $response->withStatus($code, $reasonPhrase);
        foreach ($headers as $header) {
            [$headerName, $headerValue] = explode(':', $header, 2);
            $response = $response->withAddedHeader($headerName, $headerValue);
        }

        $response->getBody()->write($content);
        $response->getBody()->rewind();
        return $response;
    }

    /**
     * Returns HTTP status code of the response
     * @param array $responseHeaders
     * @return int
     */
    private function getStatusCode(array $responseHeaders): int
    {
        $headers = $this->normalizeResponseHeaders($responseHeaders);
        $statusLine = array_shift($headers);
        [$code] = $this->getHttpStatus($statusLine);
        return (int)$code;
    }

    /**
     * Builds headers list in a form "name: value"
     */
    protected function buildHeaders(array $headers, string $host): array
    {
        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            $canonicalName = strtolower(trim($name));
            $requestHeaders[$canonicalName] = $value;
        }
        // Force correct Host header, override if it was provided in $headers
        $requestHeaders['host'] = $host;
        $requestHeaders['user-agent'] = $this->getUserAgent();
        
        return array_map(function ($k, $v) {
            return "$k: $v";
        }, array_keys($requestHeaders), array_values($requestHeaders));
    }

    /**
     * Normalizes response headers
     * @param array $responseHeaders
     * @return array
     */
    protected function normalizeResponseHeaders(array $responseHeaders): array
    {
        $headers = [];
        foreach ($responseHeaders as $header) {
            $trimmed = trim($header);
            if (0 === stripos($trimmed, 'http/')) {
                $headers = [];
                $headers[] = $trimmed;
                continue;
            }

            if (false === strpos($trimmed, ':')) {
                continue;
            }
            $headers[] = $trimmed;
        }
        return $headers;
    }

    /**
     * Returns HTTP status code and the reason from the provided status line
     * @param $statusLine
     * @return array
     */
    protected function getHttpStatus($statusLine): array
    {
        $parts = explode(' ', $statusLine, 3);
        if (count($parts) < 2 || 0 !== stripos($parts[0], 'http/')) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid HTTP status line', $statusLine));
        }
        $code = $parts[1];
        $reasonPhrase = $parts[2] ?? '';
        return [$code, $reasonPhrase];
    }

    /**
     * Calculates the wait time for the next retry.
     *
     * @param int $attempt The retry attempt count.
     *
     * @return int The delay in microseconds.
     */
    private function getExponentialBackoffDelayInMicroseconds(int $attempt): int
    {
        $oneSecondInMicroseconds = 1000000;
        $maxDelay = 64 * $oneSecondInMicroseconds;
        $delay = 2 ** $attempt * $oneSecondInMicroseconds;
        $jitter = random_int(0, $oneSecondInMicroseconds);

        return min($delay + $jitter, $maxDelay);
    }

    /**
     * Validates URL format and scheme
     */
    private function validateUrl(string $url): void
    {
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid url was provided.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid url scheme. Only HTTP and HTTPS are supported.');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            throw new \InvalidArgumentException('Invalid url: missing host.');
        }
    }

    /**
     * Resolves target URL for the request (converts hostname to IP for secure requests)
     */
    private function resolveTargetUrl(string $url, string $host): string
    {
        // If host is trusted, use original URL
        if (in_array($host, $this->trustedHosts, true)) {
            return $url;
        }

        // If host is already an IP address, use original URL
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $url;
        }

        // Resolve hostname to IP for security
        $resolver = $this->resolver ?? new SecureNativeResolver(SecurityPolicy::createStrict());
        $ipAddress = $resolver->resolveToIp($host);
        
        // Replace hostname with IP in URL
        return $this->convertHostToIp($url, $ipAddress->toString());
    }

    /**
     * Converts hostname in URL to IP address
     */
    private function convertHostToIp(string $url, string $ip): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid URL provided');
        }
        $parts['host'] = $ip;
        return $this->buildUrlFromParts($parts);
    }

    /**
     * Builds URL from parsed components
     */
    private function buildUrlFromParts(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }

    /**
     * Resolves redirect URL according to RFC 2396 section 5.2
     */
    private function resolveRedirectUrl(string $redirectUrl, string $baseUrl): string
    {
        return Url::resolveLocation($redirectUrl, $baseUrl);
    }

    /**
     * Factory method to create HttpClient instance
     */
    public static function create(array $config = []): self
    {
        $client = new self();
        
        if (!empty($config['timeout'])) {
            $client->setTimeout($config['timeout']);
        }
        if (!empty($config['maxRedirects'])) {
            $client->setMaxRedirects($config['maxRedirects']);
        }
        if (!empty($config['userAgent'])) {
            $client->setUserAgent($config['userAgent']);
        }
        if (!empty($config['resolver'])) {
            $client->setResolver($config['resolver']);
        }
        if (!empty($config['trustedHosts'])) {
            $client->trustTo(...$config['trustedHosts']);
        }
        
        return $client;
    }
} 