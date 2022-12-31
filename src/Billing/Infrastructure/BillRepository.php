<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\BillRepositoryInterface;
use App\Billing\Domain\FileRequest;
use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Report\Extra;
use App\Billing\Model\Report\Parameters;
use App\Billing\Model\Report\User;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Infrastructure\Service\SerializerService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class BillRepository implements BillRepositoryInterface
{
    private SerializerService $serializer;

    public function __construct(
        SerializerService $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function sendBill(Bill $invoice): DocumentResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($invoice, 'json');
        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/invoice/send?token=123456', [
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
    public function getPdf(Bill $invoice): string
    {
        $httpClient = HttpClient::create();
        $fileRequest = new FileRequest($invoice, new Parameters(
            user: new User(
                extras: [
                    new Extra('FORMA DE PAGO', 'CONTADO'),
                    new Extra('OBS', $invoice->obs),
                ],
            )
        ));
        $requestBody = $this->serializer->serialize($fileRequest, 'json');

        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/invoice/pdf?token=123456', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $requestBody,
        ]);

        return $response->getContent();
    }
}
