<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Infrastructure;

use App\EInvoicing\Billing\Domain\FileRequest;
use App\EInvoicing\Billing\Domain\NoteRepositoryInterface;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Sale\Note;
use Override;
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
        $response = $this->httpClient->request('POST', $url, [
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
}
