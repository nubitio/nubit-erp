<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Infrastructure;

use Override;
use Nubit\Platform\Tenant\Context\TenantContext;
use App\EInvoicing\Billing\Domain\BillRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\EInvoicing\Billing\Domain\FileRequest;
use App\EInvoicing\Billing\Model\Report\Extra;
use App\EInvoicing\Billing\Model\Report\Parameters;
use App\EInvoicing\Billing\Model\Report\User;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class BillRepository implements BillRepositoryInterface
{
    use LycetUrlBuilder;

    public function __construct(
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private LoggerInterface $billingLogger,
        private HttpClientInterface $httpClient,
        private TenantContext $tenantContext,
        #[Autowire('%env(BILL_API_BASE_URL)%')]
        private string $apiBaseUrl,
        #[Autowire('%env(BILL_API_TOKEN)%')]
        private string $apiToken,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    #[Override]
    public function sendBill(Invoice $invoice): DocumentResponse
    {
        $requestBody = $this->serializer->serialize($invoice, 'json');
        $url = $this->lycetUrl('invoice/send');
        $safeUrl = $this->lycetUrlSafe('invoice/send');
        $taxDocumentId = $invoice->getSerie() . '-' . $invoice->getCorrelativo();
        $tenantId = (string) ($this->tenantContext->getTenantId() ?? 'unknown');

        $this->logger->info('Enviando factura a Bill', ['invoice' => $invoice]);
        $this->logger->info('Request Body', ['requestBody' => $requestBody]);

        $start = hrtime(true);
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $requestBody,
            ]);

            $responseBody = $response->getContent();
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $this->logger->info('Response Body', ['responseBody' => $responseBody]);

            /** @var DocumentResponse $documentResponse */
            $documentResponse = $this->serializer->deserialize($responseBody, DocumentResponse::class, 'json');

            $sunatResponse = $documentResponse->sunatResponse;
            $cdr = $sunatResponse->getCdrResponse();
            $success = (bool) $sunatResponse->isSuccess();
            $sunatCode = $cdr?->getCode() ?? '';
            $sunatMessage = $cdr?->getDescription() ?? '';

            $logContext = [
                'sunat_code'      => $sunatCode,
                'sunat_message'   => $sunatMessage,
                'success'         => $success,
                'duration_ms'     => $durationMs,
                'tenant_id'       => $tenantId,
                'tax_document_id' => $taxDocumentId,
            ];

            if ($success) {
                $this->billingLogger->info('sunat.send', $logContext);
            } else {
                $this->billingLogger->warning('sunat.send', $logContext);
            }

            return $documentResponse;
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $this->billingLogger->error('sunat.send', [
                'sunat_code'      => '',
                'sunat_message'   => '',
                'success'         => false,
                'duration_ms'     => $durationMs,
                'tenant_id'       => $tenantId,
                'tax_document_id' => $taxDocumentId,
                'url'             => $safeUrl,
                'exception'       => $e,
            ]);

            $this->maskAndRethrow($e);
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    #[Override]
    public function getXml(Invoice $invoice): string
    {
        $requestBody = $this->serializer->serialize($invoice, 'json');
        $url = $this->lycetUrl('invoice/xml');

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $requestBody,
        ]);

        return $response->getContent();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    #[Override]
    public function getPdf(Invoice $invoice): string
    {
        $fileRequest = new FileRequest(
            $invoice,
            new Parameters(
                user: new User(
                    extras: [
                        new Extra(
                            'FORMA DE PAGO',
                            $invoice->getFormaPago() instanceof FormaPagoCredito ? 'CREDITO' : 'CONTADO'
                        ),
                        new Extra('OBS', strval($invoice->getObservacion())),
                    ],
                )
            )
        );

        $requestBody = $this->serializer->serialize($fileRequest, 'json');
        $url = $this->lycetUrl('invoice/pdf');

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $requestBody,
        ]);

        return $response->getContent();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    #[Override]
    public function getStatus(Invoice $invoice): StatusCdrResult
    {
        $params = [
            'ruc' => $invoice->getCompany()?->getRuc(),
            'tipo' => $invoice->getTipoDoc(),
            'serie' => $invoice->getSerie(),
            'numero' => $invoice->getCorrelativo(),
        ];

        $url = $this->lycetUrl('invoice/status', $params);

        $response = $this->httpClient->request('GET', $url);

        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
