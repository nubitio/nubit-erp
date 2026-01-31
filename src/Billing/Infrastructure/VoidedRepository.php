<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure;

use App\Billing\Domain\VoidedRepositoryInterface;
use App\Billing\Model\Response\SummaryResponse;
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
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
        private HttpClientInterface $httpClient,
        #[Autowire('%env(BILL_API_BASE_URL)%')]
        private string $apiBaseUrl,
        #[Autowire('%env(BILL_API_TOKEN)%')]
        private string $apiToken,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function getLastCorrelativeByDate(string $date): int
    {
        $sql = 'SELECT COUNT(*) AS correlative FROM movimiento WHERE estado = 2 AND fecha = ?';
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
     * @throws ExceptionInterface
     */
    #[Override]
    public function voidInvoice(Voided $voided): SummaryResponse
    {
        $requestBody = $this->serializer->serialize($voided, 'json');
        $url = $this->apiBaseUrl . 'voided/send?token=' . $this->apiToken;
        $response = $this->httpClient->request('POST', $url, [
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
     * @throws ExceptionInterface
     */
    #[Override]
    public function getStatus(string $ticket, string $ruc): StatusCdrResult
    {
        $url = $this->apiBaseUrl . 'voided/status?token=' . $this->apiToken . '&ticket=' . $ticket . '&ruc=' . $ruc;
        $response = $this->httpClient->request('GET', $url);
        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
