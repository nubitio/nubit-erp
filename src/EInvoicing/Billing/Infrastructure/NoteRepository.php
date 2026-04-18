<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Infrastructure;

use App\EInvoicing\Billing\Domain\FileRequest;
use App\EInvoicing\Billing\Domain\NoteRepositoryInterface;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Note;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class NoteRepository implements NoteRepositoryInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private HttpClientInterface $httpClient,
        private LoggerInterface $billingLogger,
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
    public function send(Note $note): DocumentResponse
    {
        $url = $this->apiBaseUrl . 'note/send?token=' . $this->apiToken;
        $requestBody = $this->serializer->serialize($note, 'json');

        $tenantId = $note->getCompany()?->getRuc() ?? 'unknown';
        $taxDocumentId = $note->getName();

        $start = hrtime(true);
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $requestBody,
            ]);
            $responseBody = $response->getContent();
            /** @var DocumentResponse $documentResponse */
            $documentResponse = $this->serializer->deserialize($responseBody, DocumentResponse::class, 'json');
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->billingLogger->error('sunat.send', [
                'sunat_code'      => 'N/A',
                'sunat_message'   => 'N/A',
                'success'         => false,
                'duration_ms'     => $durationMs,
                'tenant_id'       => $tenantId,
                'tax_document_id' => $taxDocumentId,
                'exception'       => $e,
            ]);
            throw $e;
        }

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $sunatResponse = $documentResponse->sunatResponse;
        $success = $sunatResponse->isSuccess() ?? false;
        $sunatCode = $sunatResponse->getError()?->getCode() ?? '';
        $sunatMessage = $sunatResponse->getError()?->getMessage() ?? '';

        if ($success) {
            $this->billingLogger->info('sunat.send', [
                'sunat_code'      => $sunatCode,
                'sunat_message'   => $sunatMessage,
                'success'         => true,
                'duration_ms'     => $durationMs,
                'tenant_id'       => $tenantId,
                'tax_document_id' => $taxDocumentId,
            ]);
        } else {
            $this->billingLogger->warning('sunat.send', [
                'sunat_code'      => $sunatCode,
                'sunat_message'   => $sunatMessage,
                'success'         => false,
                'duration_ms'     => $durationMs,
                'tenant_id'       => $tenantId,
                'tax_document_id' => $taxDocumentId,
            ]);
        }

        return $documentResponse;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    #[Override]
    public function pdf(Note $note): string
    {
        $fileRequest = new FileRequest($note);
        $requestBody = $this->serializer->serialize($fileRequest, 'json');
        $url = $this->apiBaseUrl . 'note/pdf?token=' . $this->apiToken;
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
    public function getStatus(Note $note): StatusCdrResult
    {
        $params = [
            'ruc' => $note->getCompany()?->getRuc(),
            'tipo' => $note->getTipoDoc(),
            'serie' => $note->getSerie(),
            'numero' => $note->getCorrelativo(),
        ];

        $urlParams = http_build_query($params);
        $baseUrl = $this->apiBaseUrl . 'note/status?token=' . $this->apiToken;
        $url = $baseUrl . ('&' . $urlParams);

        $response = $this->httpClient->request('GET', $url);

        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
