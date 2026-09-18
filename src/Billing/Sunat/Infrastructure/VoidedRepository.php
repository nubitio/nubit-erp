<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Infrastructure;

use App\Billing\Sunat\Domain\VoidedRepositoryInterface;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Voided\Voided;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class VoidedRepository implements VoidedRepositoryInterface
{
    use LycetUrlBuilder;

    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
        private HttpClientInterface $httpClient,
        #[Autowire('%env(BILL_API_BASE_URL)%')]
        private string $apiBaseUrl,
        #[Autowire('%env(BILL_API_TOKEN)%')]
        private string $apiToken,
    ) {}

    /**
     * @throws Exception
     */
    #[Override]
    public function getLastCorrelativeByDate(string $date): int
    {
        $count = $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) AS correlative
                FROM tax_document td
                INNER JOIN sales s ON (td.sale_id = s.id OR td.movimiento_id = s.id)
                WHERE td.document_type IN ('01', '03')
                  AND s.status = 'VOIDED'
                  AND td.issue_date = :date_value
                SQL,
            [
                'date_value' => $date,
            ],
        );

        return (int) ($count !== false ? $count : 0);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    #[Override]
    public function voidInvoice(Voided $voided): SummaryResponse
    {
        $requestBody = $this->serializer->serialize($voided, 'json');
        $url = $this->lycetUrl('voided/send');
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $requestBody,
            ]);
            $responseBody = $response->getContent();
        } catch (\Throwable $e) {
            $this->maskAndRethrow($e);
        }
        /** @var SummaryResponse $documentResponse */
        $documentResponse = $this->serializer->deserialize($responseBody, SummaryResponse::class, 'json');

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
    public function getStatus(string $ticket, string $ruc): StatusCdrResult
    {
        $url = $this->lycetUrl('voided/status', ['ticket' => $ticket, 'ruc' => $ruc]);
        $response = $this->httpClient->request('GET', $url);
        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
