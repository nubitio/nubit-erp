<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Infrastructure;

use App\Billing\Sunat\Domain\LycetCompanyServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implementación HTTP del servicio de gestión de empresas en lycet.
 *
 * Llama a los endpoints:
 *   PUT  /api/v1/configuration/company/{ruc}
 *   PUT  /api/v1/configuration/company/{ruc}/logo
 *   DELETE /api/v1/configuration/company/{ruc}
 */
readonly class LycetCompanyService implements LycetCompanyServiceInterface
{
    use LycetUrlBuilder;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(BILL_API_BASE_URL)%')]
        private string $apiBaseUrl,
        #[Autowire('%env(BILL_API_TOKEN)%')]
        private string $apiToken,
    ) {}

    /**
     * @param array<string,string> $urls
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function upsertCompany(
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        array $urls = [],
        ?string $clientId = null,
        ?string $clientSecret = null,
    ): void {
        $url = $this->lycetUrl("configuration/company/{$ruc}");

        $payload = [
            'SOL_USER' => $solUser,
            'SOL_PASS' => $solPass,
            'certificate' => base64_encode($certPem),
        ];

        foreach (['FE_URL', 'RE_URL', 'GUIA_URL', 'AUTH_URL', 'API_URL'] as $key) {
            if (!empty($urls[$key])) {
                $payload[$key] = $urls[$key];
            }
        }

        if ($clientId !== null && $clientId !== '') {
            $payload['CLIENT_ID'] = $clientId;
        }

        if ($clientSecret !== null && $clientSecret !== '') {
            $payload['CLIENT_SECRET'] = $clientSecret;
        }

        $this->logger->info('lycet.company.upsert', ['ruc' => $ruc]);

        try {
            $response = $this->httpClient->request('PUT', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
            ]);

            $response->getContent(); // throws on 4xx/5xx
        } catch (\Throwable $e) {
            $this->maskAndRethrow($e);
        }

        $this->logger->info('lycet.company.upsert.ok', ['ruc' => $ruc]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function uploadCompanyLogo(string $ruc, string $imageData): void
    {
        $url = $this->lycetUrl("configuration/company/{$ruc}/logo");

        $this->logger->info('lycet.company.logo.upload', ['ruc' => $ruc]);

        try {
            $response = $this->httpClient->request('PUT', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['logo' => base64_encode($imageData)],
            ]);

            $response->getContent();
        } catch (\Throwable $e) {
            $this->maskAndRethrow($e);
        }

        $this->logger->info('lycet.company.logo.upload.ok', ['ruc' => $ruc]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function removeCompany(string $ruc): void
    {
        $url = $this->lycetUrl("configuration/company/{$ruc}");

        $this->logger->info('lycet.company.remove', ['ruc' => $ruc]);

        try {
            $response = $this->httpClient->request('DELETE', $url);
            $response->getContent();
        } catch (\Throwable $e) {
            $this->maskAndRethrow($e);
        }

        $this->logger->info('lycet.company.remove.ok', ['ruc' => $ruc]);
    }
}
