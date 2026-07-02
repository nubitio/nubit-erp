<?php

declare(strict_types=1);

namespace App\AI\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AI\ApiResource\InvokeHydraOperation;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Executes a mutating HTTP operation (POST, PATCH, PUT, DELETE) against the Efact API.
 *
 * @implements ProcessorInterface<InvokeHydraOperation, CallToolResult>
 */
readonly class InvokeHydraOperationProcessor implements ProcessorInterface
{
    private const array ALLOWED_METHODS = ['POST', 'PATCH', 'PUT', 'DELETE'];

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%app.api_base_url%')]
        private string $apiBaseUrl,
    ) {}

    /**
     * @param InvokeHydraOperation $data
     */
    public function process(
        mixed $data,
        Operation $_operation,
        array $_uriVariables = [],
        array $_context = [],
    ): CallToolResult {
        $method = strtoupper($data->method);

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return CallToolResult::error([
                new TextContent(sprintf(
                    'Method "%s" is not allowed. Use one of: %s. For GET requests use the read_hydra_resource tool.',
                    $method,
                    implode(', ', self::ALLOWED_METHODS),
                )),
            ]);
        }

        $uri = $data->uri;

        // If the URI is a relative path, prepend the base URL
        if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://')) {
            $uri = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($uri, '/');
        }

        $options = [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
        ];

        if (null !== $data->payload && $method !== 'DELETE') {
            $options['json'] = $data->payload;
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);

            if ($statusCode >= 400) {
                return CallToolResult::error([
                    new TextContent(sprintf('HTTP %d: %s', $statusCode, $body)),
                ]);
            }

            // 204 No Content (e.g. DELETE success) — return a helpful message
            if ($statusCode === 204 || $body === '') {
                return CallToolResult::success([
                    new TextContent(sprintf('Success: HTTP %d', $statusCode)),
                ]);
            }

            return CallToolResult::success([
                new TextContent($body),
            ]);
        } catch (TransportExceptionInterface $e) {
            return CallToolResult::error([
                new TextContent(sprintf('Transport error: %s', $e->getMessage())),
            ]);
        }
    }
}
