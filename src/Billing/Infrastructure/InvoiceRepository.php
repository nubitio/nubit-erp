<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\InvoiceRepositoryInterface;
use App\Billing\Model\Invoice\Invoice;
use App\Billing\Model\Response\DocumentResponse;
use App\Domain\Service\SerializerService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class InvoiceRepository implements InvoiceRepositoryInterface
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
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function createInvoice(Invoice $invoice): DocumentResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($invoice, 'json');
        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/invoice/send?token=123456', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body'    => $requestBody,
        ]);
        $responseBody = $response->getContent();
        /** @var DocumentResponse $documentResponse */
        $documentResponse = $this->serializer->deserialize($responseBody, DocumentResponse::class, 'json');
        return $documentResponse;
    }
}