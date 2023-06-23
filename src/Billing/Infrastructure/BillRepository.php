<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\BillConstants;
use App\Billing\Domain\BillRepositoryInterface;
use App\Billing\Domain\FileRequest;
use App\Billing\Model\Report\Extra;
use App\Billing\Model\Report\Parameters;
use App\Billing\Model\Report\User;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Infrastructure\Service\SerializerService;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Invoice;
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
    public function sendBill(Invoice $invoice): DocumentResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($invoice, 'json');
        $url = BillConstants::BILL_API_BASE_URL.'invoice/send?token='.BillConstants::BILL_API_TOKEN;

        $response = $httpClient->request('POST', $url, [
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
    public function getXml(Invoice $invoice): string
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($invoice, 'json');
        $url = BillConstants::BILL_API_BASE_URL.'invoice/xml?token='.BillConstants::BILL_API_TOKEN;

        $response = $httpClient->request('POST', $url, [
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
     */
    public function getPdf(Invoice $invoice): string
    {
        $httpClient = HttpClient::create();

        $fileRequest = new FileRequest($invoice, new Parameters(
            user: new User(
                extras: [
                    new Extra('FORMA DE PAGO', 'CONTADO'),
                    new Extra('OBS', strval($invoice->getObservacion())),
                ],
            )
        ));

        $requestBody = $this->serializer->serialize($fileRequest, 'json');
        $url = BillConstants::BILL_API_BASE_URL.'invoice/pdf?token='.BillConstants::BILL_API_TOKEN;

        $response = $httpClient->request('POST', $url, [
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
     */
    public function getStatus(Invoice $invoice): StatusCdrResult
    {
        $httpClient = HttpClient::create();

        $params = [
            'ruc' => $invoice->getCompany()?->getRuc(),
            'tipo' => $invoice->getTipoDoc(),
            'serie' => $invoice->getSerie(),
            'numero' => $invoice->getCorrelativo(),
        ];

        $urlParams = http_build_query($params);
        $url = BillConstants::BILL_API_BASE_URL.'invoice/status?token='.BillConstants::BILL_API_TOKEN."&$urlParams";

        $response = $httpClient->request('GET', $url);

        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
