<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\VoidedRepositoryInterface;
use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Response\StatusResponse;
use App\Billing\Model\Voided\Voided;
use App\Core\Infrastructure\Service\SerializerService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class VoidedRepository implements VoidedRepositoryInterface
{
    private Connection $connection;
    private SerializerService $serializer;

    public function __construct(
        Connection $connection,
        SerializerService $serializer
    ) {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    /**
     * @throws Exception
     */
    public function getLastCorrelativeByDate(string $date): int
    {
        $sql = 'SELECT COUNT(*) AS correlative FROM vw_movimiento WHERE estado = 2 AND fecha = ?';
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
    public function voidInvoice(Voided $voided): DocumentResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($voided, 'json');
        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/voided/send?token=123456', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $requestBody,
        ]);
        $responseBody = $response->getContent();
        /** @var DocumentResponse $documentResponse */
        $documentResponse = $this->serializer->deserialize($responseBody, DocumentResponse::class, 'json');

        return $documentResponse;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getStatus(string $ticket, string $ruc): StatusResponse
    {
        $httpClient = HttpClient::create();
        $response = $httpClient->request('GET', 'https://api.efact.app/api/v1/voided/status?token=123456&ticket='.$ticket.'&ruc='.$ruc);
        $responseBody = $response->getContent();
        /** @var StatusResponse $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusResponse::class, 'json');

        return $statusResponse;
    }
}
