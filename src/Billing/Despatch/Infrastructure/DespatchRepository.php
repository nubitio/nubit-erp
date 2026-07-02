<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Infrastructure;

use App\Billing\Despatch\Domain\DespatchRepositoryInterface;
use App\Billing\Sunat\Infrastructure\LycetUrlBuilder;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Despatch\Despatch;
use Nubit\Platform\Tenant\Context\TenantContext;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente lycet para el envío de Guías de Remisión Electrónica (GRE).
 *
 * Replica el contrato de {@see \App\Billing\Sunat\Infrastructure\BillRepository}
 * apuntando al endpoint de despacho de lycet (despatch/send). El token nunca se
 * registra en logs ni trazas (ver LycetUrlBuilder::maskAndRethrow()).
 */
readonly class DespatchRepository implements DespatchRepositoryInterface
{
    use LycetUrlBuilder;

    public function __construct(
        private SerializerInterface $serializer,
        private LoggerInterface $billingLogger,
        private HttpClientInterface $httpClient,
        private TenantContext $tenantContext,
        #[Autowire('%env(BILL_API_BASE_URL)%')]
        private string $apiBaseUrl,
        #[Autowire('%env(BILL_API_TOKEN)%')]
        private string $apiToken,
    ) {}

    #[Override]
    public function sendDespatch(Despatch $despatch): DocumentResponse
    {
        $requestBody = $this->serializer->serialize($despatch, 'json');
        $url = $this->lycetUrl('despatch/send');
        $safeUrl = $this->lycetUrlSafe('despatch/send');
        $documentId = $despatch->getSerie() . '-' . $despatch->getCorrelativo();
        $tenantId = (string) ($this->tenantContext->getTenantId() ?? 'unknown');

        $start = hrtime(true);
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $requestBody,
            ]);

            $responseBody = $response->getContent();
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            /** @var DocumentResponse $documentResponse */
            $documentResponse = $this->serializer->deserialize($responseBody, DocumentResponse::class, 'json');

            $sunatResponse = $documentResponse->sunatResponse;
            $cdr = $sunatResponse->getCdrResponse();
            $success = (bool) $sunatResponse->isSuccess();

            $logContext = [
                'sunat_code' => $cdr?->getCode() ?? '',
                'sunat_message' => $cdr?->getDescription() ?? '',
                'success' => $success,
                'duration_ms' => $durationMs,
                'tenant_id' => $tenantId,
                'despatch_id' => $documentId,
            ];

            if ($success) {
                $this->billingLogger->info('sunat.despatch.send', $logContext);
            } else {
                $this->billingLogger->warning('sunat.despatch.send', $logContext);
            }

            return $documentResponse;
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $this->billingLogger->error('sunat.despatch.send', [
                'success' => false,
                'duration_ms' => $durationMs,
                'tenant_id' => $tenantId,
                'despatch_id' => $documentId,
                'url' => $safeUrl,
                'exception' => $e,
            ]);

            $this->maskAndRethrow($e);
        }
    }
}
