<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Billing\Infrastructure;

use App\EInvoicing\Billing\Infrastructure\VoidedRepository;
use App\EInvoicing\Billing\Model\Response\SummaryResponse;
use Doctrine\DBAL\Connection;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Voided\Voided;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[AllowMockObjectsWithoutExpectations]
final class VoidedRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private SerializerInterface&MockObject $serializer;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    private function makeRepository(
        MockHttpClient $httpClient,
        string $apiBaseUrl = 'https://api.example.com/',
        string $apiToken = 'test-token',
    ): VoidedRepository {
        return new VoidedRepository(
            $this->connection,
            $this->serializer,
            $httpClient,
            $apiBaseUrl,
            $apiToken,
        );
    }

    // -----------------------------------------------------------------------
    // getLastCorrelativeByDate — tableExists returns true (new flow)
    // -----------------------------------------------------------------------

    public function testGetLastCorrelativeByDateReturnsCountWhenTablesExist(): void
    {
        // tableExists('sales') and tableExists('tax_document') both return truthy (1)
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params): mixed {
                if (str_contains($sql, 'information_schema')) {
                    return '1'; // table exists
                }
                if (str_contains($sql, 'COUNT(*)')) {
                    return '3'; // 3 voided records
                }
                return false;
            });

        $httpClient = new MockHttpClient();
        $repo = $this->makeRepository($httpClient);

        $result = $repo->getLastCorrelativeByDate('2025-01-15');

        self::assertSame(3, $result);
    }

    public function testGetLastCorrelativeByDateCountsV2NativeSalesWithSaleId(): void
    {
        // Simulates a tenant where sales are created natively in V2 (sale_id populated,
        // legacy_movimiento_id = NULL). The JOIN must cover td.sale_id = s.id path.
        // The repository query uses a single COUNT that already covers both join arms,
        // so the test verifies the SQL contains the sale_id join condition.
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params): mixed {
                if (str_contains($sql, 'information_schema')) {
                    return '1'; // table exists
                }
                if (str_contains($sql, 'COUNT(*)')) {
                    // Verify both join arms are present in the query
                    self::assertStringContainsString('td.sale_id = s.id', $sql);
                    self::assertStringContainsString('s.legacy_movimiento_id = td.movimiento_id', $sql);

                    return '5'; // 5 voided records (mix of legacy + V2 native)
                }
                return false;
            });

        $httpClient = new MockHttpClient();
        $repo = $this->makeRepository($httpClient);

        $result = $repo->getLastCorrelativeByDate('2025-06-01');

        self::assertSame(5, $result);
    }

    public function testGetLastCorrelativeByDateReturnsZeroWhenLegacyResultIsEmpty(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        $statement = $this->createMock(\Doctrine\DBAL\Result::class);
        $statement->method('fetchAssociative')->willReturn(false);
        $this->connection->method('executeQuery')->willReturn($statement);

        $httpClient = new MockHttpClient();
        $repo = $this->makeRepository($httpClient);

        $result = $repo->getLastCorrelativeByDate('2025-01-15');

        self::assertSame(0, $result);
    }

    // -----------------------------------------------------------------------
    // voidInvoice
    // -----------------------------------------------------------------------

    public function testVoidInvoiceSerializesAndPostsAndReturnsResponse(): void
    {
        $voided = new Voided();
        $expectedResponse = new SummaryResponse();

        $this->serializer->method('serialize')->willReturn('{"some":"json"}');
        $this->serializer->method('deserialize')->willReturn($expectedResponse);

        $mockResponse = new MockResponse('{"name":"RA-001"}', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);

        $repo   = $this->makeRepository($httpClient, 'https://api.example.com/', 'my-token');
        $result = $repo->voidInvoice($voided);

        self::assertSame($expectedResponse, $result);
    }

    public function testVoidInvoiceCallsCorrectUrlWithToken(): void
    {
        $voided = new Voided();

        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn(new SummaryResponse());

        $capturedUrl = null;
        $mockResponse = new MockResponse('{}', ['http_code' => 200]);
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl, $mockResponse): MockResponse {
            $capturedUrl = $url;
            return $mockResponse;
        });

        $repo = $this->makeRepository($httpClient, 'https://api.example.com/', 'secret-token');
        $repo->voidInvoice($voided);

        self::assertStringContainsString('voided/send', (string) $capturedUrl);
        self::assertStringContainsString('secret-token', (string) $capturedUrl);
    }

    // -----------------------------------------------------------------------
    // getStatus
    // -----------------------------------------------------------------------

    public function testGetStatusCallsCorrectUrlAndReturnsResult(): void
    {
        $expectedResult = new StatusCdrResult();

        $this->serializer->method('deserialize')->willReturn($expectedResult);

        $capturedUrl = null;
        $mockResponse = new MockResponse('{}', ['http_code' => 200]);
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl, $mockResponse): MockResponse {
            $capturedUrl = $url;
            return $mockResponse;
        });

        $repo   = $this->makeRepository($httpClient, 'https://api.example.com/', 'test-token');
        $result = $repo->getStatus('TICKET-001', '20100000000');

        self::assertSame($expectedResult, $result);
        self::assertStringContainsString('voided/status', (string) $capturedUrl);
        self::assertStringContainsString('TICKET-001', (string) $capturedUrl);
        self::assertStringContainsString('20100000000', (string) $capturedUrl);
        self::assertStringContainsString('test-token', (string) $capturedUrl);
    }

    public function testGetStatusUsesGetHttpMethod(): void
    {
        $this->serializer->method('deserialize')->willReturn(new StatusCdrResult());

        $capturedMethod = null;
        $mockResponse = new MockResponse('{}', ['http_code' => 200]);
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedMethod, $mockResponse): MockResponse {
            $capturedMethod = $method;
            return $mockResponse;
        });

        $repo = $this->makeRepository($httpClient);
        $repo->getStatus('T001', '20100000000');

        self::assertSame('GET', $capturedMethod);
    }
}
