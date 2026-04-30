<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Service;

use App\Catalog\Service\ExchangeRateSyncService;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
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
    private SettingsService&MockObject $settings;
    private LoggerInterface&MockObject $logger;
    private ExchangeRateSyncService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->settings   = $this->createMock(SettingsService::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->service = new ExchangeRateSyncService(
            $this->settings,
            $this->httpClient,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testSyncHappyPathReturnsSuccessAndSavesSettings(): void
    {
        $bcrpData = ['periods' => [['values' => ['3.750000']]]];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($bcrpData);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $this->settings
            ->expects(self::exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $key) {
                self::assertContains($key, [
                    Constants::SALES_DEFAULT_USD_EXCHANGE_RATE,
                    Constants::EXCHANGE_RATE_UPDATED_AT,
                ]);
            });

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        self::assertSame('3.750000', $result['rate']);
        self::assertNotNull($result['updated_at']);
        self::assertSame('Rate synced successfully', $result['message']);
    }

    // -------------------------------------------------------------------------
    // n.d. value — all periods invalid
    // -------------------------------------------------------------------------

    public function testSyncWithNdValueReturnsFailureAndLogsWarning(): void
    {
        $bcrpData = ['periods' => [['values' => ['n.d.']]]];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($bcrpData);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger
            ->expects(self::once())
            ->method('warning');

        $this->settings
            ->expects(self::never())
            ->method('set');

        $result = $this->service->sync();

        self::assertFalse($result['success']);
        self::assertNull($result['rate']);
        self::assertNull($result['updated_at']);
    }

    // -------------------------------------------------------------------------
    // Empty periods array
    // -------------------------------------------------------------------------

    public function testSyncWithEmptyPeriodsReturnsFailure(): void
    {
        $bcrpData = ['periods' => []];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($bcrpData);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects(self::once())->method('warning');
        $this->settings->expects(self::never())->method('set');

        $result = $this->service->sync();

        self::assertFalse($result['success']);
        self::assertNull($result['rate']);
    }

    // -------------------------------------------------------------------------
    // HTTP exception
    // -------------------------------------------------------------------------

    public function testSyncLogsWarningAndReturnsFailureOnHttpException(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new RuntimeException('Connection refused'));

        $this->logger
            ->expects(self::once())
            ->method('warning');

        $this->settings
            ->expects(self::never())
            ->method('set');

        $result = $this->service->sync();

        self::assertFalse($result['success']);
        self::assertNull($result['rate']);
        self::assertNull($result['updated_at']);
        self::assertSame('Connection refused', $result['message']);
    }

    // -------------------------------------------------------------------------
    // Multiple periods — picks last valid (reversed iteration)
    // -------------------------------------------------------------------------

    public function testSyncPicksLastValidPeriodWhenMultiplePeriods(): void
    {
        // Reversed iteration means the last element in the array is checked first.
        // So the last period with a valid value wins.
        $bcrpData = [
            'periods' => [
                ['values' => ['3.700000']],
                ['values' => ['n.d.']],
                ['values' => ['3.850000']],
            ],
        ];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($bcrpData);

        $this->httpClient->method('request')->willReturn($response);

        $this->settings->expects(self::exactly(2))->method('set');

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        // array_reverse picks index 2 first (3.850000), which is valid
        self::assertSame('3.850000', $result['rate']);
    }

    public function testSyncSkipsNdAndPicksFirstValidInReversedOrder(): void
    {
        // Last period is n.d., second-to-last is valid
        $bcrpData = [
            'periods' => [
                ['values' => ['3.700000']],
                ['values' => ['3.750000']],
                ['values' => ['n.d.']],
            ],
        ];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($bcrpData);

        $this->httpClient->method('request')->willReturn($response);
        $this->settings->expects(self::exactly(2))->method('set');

        $result = $this->service->sync();

        self::assertTrue($result['success']);
        // Reversed: index 2 (n.d.) skipped, index 1 (3.750000) used
        self::assertSame('3.750000', $result['rate']);
    }
}
