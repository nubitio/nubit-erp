<?php

declare(strict_types=1);

namespace App\AI\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Result\ReadResourceResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provides the full Hydra/JSON-LD API documentation as an MCP resource.
 *
 * @implements ProviderInterface<ReadResourceResult>
 */
readonly class ApiDocsProvider implements ProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%app.api_base_url%')]
        private string $apiBaseUrl,
    ) {
    }

    /**
     */
    public function provide(Operation $_operation, array $_uriVariables = [], array $_context = []): ReadResourceResult
    {
        $docsUrl = rtrim($this->apiBaseUrl, '/') . '/api/docs.jsonld';

        try {
            $response = $this->httpClient->request('GET', $docsUrl, [
                'headers' => [
                    'Accept' => 'application/ld+json',
                ],
            ]);

            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            $body = sprintf('{"error": "Failed to fetch API docs: %s"}', addslashes($e->getMessage()));
        }

        return new ReadResourceResult([
            new TextResourceContents(
                uri: 'resource://efact/api-docs',
                mimeType: 'application/ld+json',
                text: $body,
            ),
        ]);
    }
}
