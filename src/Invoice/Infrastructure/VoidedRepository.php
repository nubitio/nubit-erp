<?php

namespace App\Invoice\Infrastructure;

use App\Domain\Service\SerializerService;
use App\Invoice\Domain\VoidedRepositoryInterface;
use App\Invoice\Model\Voided\Response\VoidedResponse;
use App\Invoice\Model\Voided\Voided;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class VoidedRepository implements VoidedRepositoryInterface
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private SerializerService $serializer;

    public function __construct(
        Connection             $connection,
        EntityManagerInterface $entityManager,
        SerializerService      $serializer
    )
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @throws Exception
     */
    public function getLastCorrelativeByDate(string $date): int
    {
        $sql = 'SELECT COUNT(*) AS correlative FROM vw_movimiento WHERE estado = 2 AND fecha = ?';
        $result = $this->connection->executeQuery($sql, [$date])->fetchAssociative();
        return $result['correlative'] ?? 0;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function voidInvoice(Voided $voided): VoidedResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($voided, 'json');
        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/voided/send?token=123456', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body'    => $requestBody,
        ]);
        $responseBody = $response->getContent();
        /** @var VoidedResponse $voidedResponse */
        $voidedResponse = $this->serializer->deserialize($responseBody, VoidedResponse::class, 'json');
        return $voidedResponse;
    }
}