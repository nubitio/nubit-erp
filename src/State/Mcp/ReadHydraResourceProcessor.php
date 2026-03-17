<?php

declare(strict_types=1);

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ReadHydraResource;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Executes a GET request against the Efact API at the given URI.
 *
 * @implements ProcessorInterface<ReadHydraResource, CallToolResult>
 */
readonly class ReadHydraResourceProcessor implements ProcessorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiBaseUrl,
    ) {
    }

    /**
     * @param ReadHydraResource $data
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function process(mixed $data, Operation $_operation, array $_uriVariables = [], array $_context = []): CallToolResult
    {
        $uri = $data->uri;

        // If the URI is a relative path, prepend the base URL
        if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://')) {
            $uri = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($uri, '/');
        }

        try {
            $response = $this->httpClient->request('GET', $uri, [
                'headers' => [
                    'Accept' => 'application/ld+json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);

            if ($statusCode >= 400) {
                return CallToolResult::error([
                    new TextContent(sprintf('HTTP %d: %s', $statusCode, $body)),
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
