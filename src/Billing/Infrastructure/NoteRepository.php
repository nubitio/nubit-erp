<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\FileRequest;
use App\Billing\Domain\NoteRepositoryInterface;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Infrastructure\Service\SerializerService;
use Greenter\Model\Sale\Note;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class NoteRepository implements NoteRepositoryInterface
{
    private SerializerService $serializer;

    public function __construct(
        SerializerService $serializer,
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function send(Note $note): DocumentResponse
    {
        $httpClient = HttpClient::create();
        $requestBody = $this->serializer->serialize($note, 'json');
        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/note/send?token=123456', [
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
    public function pdf(Note $note): string
    {
        $httpClient = HttpClient::create();
        $fileRequest = new FileRequest($note);
        $requestBody = $this->serializer->serialize($fileRequest, 'json');

        $response = $httpClient->request('POST', 'https://api.efact.app/api/v1/note/pdf?token=123456', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $requestBody,
        ]);

        return $response->getContent();
    }
}
