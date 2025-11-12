<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\SummaryRepositoryInterface;
use App\Billing\Model\Response\SummaryResponse;
use App\Core\Service\SerializerService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Summary\Summary;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class SummaryRepository implements SummaryRepositoryInterface
{
    public function __construct(private Connection $connection, private SerializerService $serializer)
    {
    }

    /**
     * @throws Exception
     */
    #[\Override]
    public function getLastCorrelativeByDate(string $date): int
    {
        $sql = 'select ifnull(max(right(correlative, 1)), 0) as correlative from summary where date = ?';
        /** @var false|array<string, bool|float|int|string|null> $result */
        $result = $this->connection->executeQuery($sql, [$date])->fetchAssociative();

        if (!$result) {
            return 0;
        }

        return intval($result['correlative']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[\Override]
    public function createSummary(Summary $summary): SummaryResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($summary, 'json');
        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/summary/send?token=123456', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $requestBody,
        ]);
        $responseBody = $response->getContent();
        /** @var SummaryResponse $documentResponse */
        $documentResponse = $this->serializer->deserialize($responseBody, SummaryResponse::class, 'json');

        return $documentResponse;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[\Override]
    public function getStatus(string $ticket, string $ruc): StatusCdrResult
    {
        $httpClient = HttpClient::create();
        $response = $httpClient->request(
            'GET',
            'https://api.efact.app/api/v1/summary/status?token=123456&ticket=' . $ticket . '&ruc=' . $ruc
        );
        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
