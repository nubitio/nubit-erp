<?php

declare(strict_types=1);

namespace App\AI\Tool;

use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTool(
    name: 'read_api_resource',
    description: 'Fetch any API resource or collection by its URI path (e.g. /api/products, /api/customers/123). '
        . 'Returns the decoded JSON response. Use this to query entities that do not have a dedicated tool. '
        . 'Always prefer dedicated tools (search_products, sales_summary, etc.) when they exist.',
    method: 'readResource',
)]
final readonly class ApiReadTool
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Security $security,
        private RequestStack $requestStack,
        #[Autowire('%app.api_base_url%')]
        private string $apiBaseUrl,
    ) {
    }

    /**
     * Fetch any API resource or collection by URI.
     *
     * @return array<string, mixed>
     */
    public function readResource(string $uri): array
    {
        // Resolve URL: prepend base URL for relative paths
        if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://')) {
            $resolvedUrl = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($uri, '/');
        } else {
            $resolvedUrl = $uri;
        }

        // Extract the JWT from the current request-scoped security token
        $token = $this->security->getToken();
        $jwt = $token instanceof JWTAuthenticationToken ? $token->getCredentials() : null;

        $headers = ['Accept' => 'application/ld+json'];
        if ($jwt !== null && $jwt !== '') {
            $headers['Authorization'] = 'Bearer ' . $jwt;
        }

        // Forward the original request's Host header so TenantListener resolves
        // the correct tenant instead of seeing the internal hostname 'web'.
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest !== null) {
            $headers['Host'] = $mainRequest->getHost();
            // Also forward X-Forwarded-Host in case of a reverse proxy in front.
            $forwardedHost = $mainRequest->headers->get('X-Forwarded-Host');
            if ($forwardedHost !== null && $forwardedHost !== '') {
                $headers['X-Forwarded-Host'] = $forwardedHost;
            }
        }

        $response = $this->httpClient->request('GET', $resolvedUrl, [
            'headers' => $headers,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw new \RuntimeException(
                sprintf('HTTP %d error fetching "%s"', $statusCode, $uri),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        return $this->normalizeHydraResponse($data);
    }

    /**
     * Strip Hydra envelope keys and rename hydra:member / hydra:totalItems.
     *
     * Collections  → { items: [...], totalItems: N }
     * Single items → original array minus @context / @id / @type
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeHydraResponse(array $data): array
    {
        if (array_key_exists('hydra:member', $data)) {
            // Collection response
            $result = [];

            /** @var list<mixed> $members */
            $members = $data['hydra:member'];
            $result['items'] = $members;

            if (array_key_exists('hydra:totalItems', $data)) {
                $result['totalItems'] = $data['hydra:totalItems'];
            }

            return $result;
        }

        // Single-resource response: strip envelope keys
        $envelope = ['@context', '@id', '@type', 'hydra:view'];
        foreach ($envelope as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
