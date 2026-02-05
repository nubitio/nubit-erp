<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Infrastructure;

use App\EInvoicing\Billing\Domain\SummaryRepositoryInterface;
use App\EInvoicing\Billing\Model\Response\SummaryResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Summary\Summary;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class SummaryRepository implements SummaryRepositoryInterface
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
     * @throws ExceptionInterface
     */
    #[Override]
    public function createSummary(Summary $summary): SummaryResponse
    {
        $url = $this->apiBaseUrl . 'summary/send?token=' . $this->apiToken;
        $requestBody = $this->serializer->serialize($summary, 'json');
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
        $url = $this->apiBaseUrl . 'summary/status?token=' . $this->apiToken . '&ticket=' . $ticket . '&ruc=' . $ruc;
        $response = $this->httpClient->request('GET', $url);
        $responseBody = $response->getContent();
        /** @var StatusCdrResult $statusResponse */
        $statusResponse = $this->serializer->deserialize($responseBody, StatusCdrResult::class, 'json');

        return $statusResponse;
    }
}
