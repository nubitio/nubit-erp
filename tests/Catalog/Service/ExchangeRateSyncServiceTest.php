<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Service;

use App\Catalog\Entity\ExchangeRate;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;
use App\Catalog\Service\ExchangeRateSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ExchangeRateSyncService::class)]
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ExchangeRateSyncServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private ExchangeRateRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private ExchangeRateSyncService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->repository = $this->createMock(ExchangeRateRepository::class);
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->service = new ExchangeRateSyncService(
            $this->repository,
            $this->em,
            $this->httpClient,
            $this->logger,
        );
    }

    private function stubBcrpResponse(array $data): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);
        $this->httpClient->method('request')->willReturn($response);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function testSyncHappyPathReturnsSuccessAndPersistsEntity(): void
    {
        $this->stubBcrpResponse(['periods' => [['values' => ['3.750000']]]]);

        $existing = new ExchangeRate('USD', '3.800000', ExchangeRateSource::BCRP_AUTO);
        $this->repository->method('findByCurrency')->willReturn($existing);
        $this->em->expects(self::once())->method('flush');

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        self::assertSame('3.750000', $result['rate']);
        self::assertNotNull($result['updated_at']);
        self::assertSame('Rate synced successfully', $result['message']);
    }

    public function testSyncCreatesNewEntityWhenNoneExists(): void
    {
        $this->stubBcrpResponse(['periods' => [['values' => ['3.750000']]]]);

        $this->repository->method('findByCurrency')->willReturn(null);
        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        self::assertSame('3.750000', $result['rate']);
    }

    // ── n.d. value ────────────────────────────────────────────────────────────

    public function testSyncWithNdValueReturnsFailureAndLogsWarning(): void
    {
        $this->stubBcrpResponse(['periods' => [['values' => ['n.d.']]]]);
        $this->logger->expects(self::once())->method('warning');
        $this->em->expects(self::never())->method('flush');

        $result = $this->service->sync();

        self::assertFalse($result['success']);
        self::assertNull($result['rate']);
        self::assertNull($result['updated_at']);
    }

    // ── Empty periods ─────────────────────────────────────────────────────────

    public function testSyncWithEmptyPeriodsReturnsFailure(): void
    {
        $this->stubBcrpResponse(['periods' => []]);
        $this->logger->expects(self::once())->method('warning');

        $result = $this->service->sync();

        self::assertFalse($result['success']);
        self::assertNull($result['rate']);
    }

    // ── HTTP exception ────────────────────────────────────────────────────────

    public function testSyncLogsWarningAndReturnsFailureOnHttpException(): void
    {
        $this->httpClient->method('request')->willThrowException(new RuntimeException('Connection refused'));
        $this->logger->expects(self::once())->method('warning');
        $this->em->expects(self::never())->method('flush');

        $result = $this->service->sync();

        self::assertFalse($result['success']);
        self::assertNull($result['rate']);
        self::assertSame('Connection refused', $result['message']);
    }

    // ── Multiple periods ──────────────────────────────────────────────────────

    public function testSyncPicksLastValidPeriodWhenMultiplePeriods(): void
    {
        $this->stubBcrpResponse(['periods' => [
            ['values' => ['3.700000']],
            ['values' => ['n.d.']],
            ['values' => ['3.850000']],
        ]]);
        $this->repository->method('findByCurrency')->willReturn(null);

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        self::assertSame('3.850000', $result['rate']);
    }

    public function testSyncSkipsNdAndPicksFirstValidInReversedOrder(): void
    {
        $this->stubBcrpResponse(['periods' => [
            ['values' => ['3.700000']],
            ['values' => ['3.750000']],
            ['values' => ['n.d.']],
        ]]);
        $this->repository->method('findByCurrency')->willReturn(null);

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        self::assertSame('3.750000', $result['rate']);
    }
}
