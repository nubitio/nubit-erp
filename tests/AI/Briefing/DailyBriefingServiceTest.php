<?php

declare(strict_types=1);

namespace App\Tests\AI\Briefing;

use App\AI\Briefing\DailyBriefingService;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Cache\CacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Unit tests for DailyBriefingService.
 *
 * AC covered:
 *   (a) Graceful empty-data fallback → default message returned, AI never called.
 *   (b) Prompt contains key data points (sales total, product name, SUNAT rejections).
 *   (c) Successful briefing is written to cache with the correct key.
 */
final class DailyBriefingServiceTest extends TestCase
{
    private AgentInterface&MockObject $agent;
    private Connection&MockObject $connection;
    private CacheManager&MockObject $cacheManager;
    private DailyBriefingService $service;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(AgentInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->cacheManager = $this->createMock(CacheManager::class);

        $this->service = new DailyBriefingService(
            $this->agent,
            $this->connection,
            $this->cacheManager,
            new NullLogger(),
        );
    }

    // -------------------------------------------------------------------------
    // (a) Graceful empty-data fallback
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testReturnsDefaultMessageWhenDataIsEmpty(): void
    {
        // Cache miss
        $this->cacheManager->method('get')->willReturn(null);

        // All DB queries return zero / empty
        $this->connection->method('fetchOne')->willReturn(0);
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        // AI must NOT be called when data is empty
        $this->agent->expects(self::never())->method('call');

        $result = $this->service->generate('demo');

        self::assertSame(DailyBriefingService::DEFAULT_MESSAGE, $result);
    }

    // -------------------------------------------------------------------------
    // (b) Prompt contains key data points
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testPromptContainsKeyDataPoints(): void
    {
        $data = [
            'sales_total' => 1234.56,
            'sales_count' => 42,
            'top_products' => [
                ['product' => 'Café Especial', 'total' => 500.00],
                ['product' => 'Pan Integral', 'total' => 300.00],
                ['product' => 'Jugo Natural', 'total' => 200.00],
            ],
            'low_stock_count' => 5,
            'sunat_rejections' => 3,
            'is_holiday' => false,
            'holiday_name' => null,
            'is_payday_week' => false,
        ];

        $prompt = $this->service->buildPrompt($data, 'acme', '2026-03-10');

        self::assertStringContainsString('1234.56', $prompt, 'Sales total must appear in prompt');
        self::assertStringContainsString('42', $prompt, 'Sales count must appear in prompt');
        self::assertStringContainsString('Café Especial', $prompt, 'Top product must appear in prompt');
        self::assertStringContainsString('5', $prompt, 'Low stock count must appear in prompt');
        self::assertStringContainsString('3', $prompt, 'SUNAT rejections must appear in prompt');
        self::assertStringContainsString('acme', $prompt, 'Tenant name must appear in prompt');
        self::assertStringContainsString('2026-03-10', $prompt, 'Date must appear in prompt');
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testPromptIncludesHolidayContext(): void
    {
        $data = [
            'sales_total' => 100.0,
            'sales_count' => 5,
            'top_products' => [],
            'low_stock_count' => 0,
            'sunat_rejections' => 0,
            'is_holiday' => true,
            'holiday_name' => 'Día de la Independencia',
            'is_payday_week' => false,
        ];

        $prompt = $this->service->buildPrompt($data, 'acme', '2026-07-28');

        self::assertStringContainsString('FERIADO', $prompt);
        self::assertStringContainsString('Día de la Independencia', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testPromptIncludesPaydayWeekContext(): void
    {
        $data = [
            'sales_total' => 100.0,
            'sales_count' => 5,
            'top_products' => [],
            'low_stock_count' => 0,
            'sunat_rejections' => 0,
            'is_holiday' => false,
            'holiday_name' => null,
            'is_payday_week' => true,
        ];

        $prompt = $this->service->buildPrompt($data, 'acme', '2026-03-31');

        self::assertStringContainsString('semana de pagos', $prompt);
    }

    // -------------------------------------------------------------------------
    // (c) Result is cached after AI call
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testBriefingIsCachedAfterAiCall(): void
    {
        $cannedBriefing = "• Ventas de ayer: S/ 1234.56\n• Top producto: Café Especial";
        $tenantName = 'acme';

        // Cache miss on first call
        $this->cacheManager->method('get')->willReturn(null);

        // DB returns non-empty data so AI is called
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls(
            '1234.56', // sales total
            '10', // sales count
            '0', // low stock count
            '0', // sunat rejections
            false, // is_holiday (time_dimension fetchOne → false)
            false, // holiday_name
        );

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['product' => 'Café Especial', 'total' => '1234.56'],
            ]);

        // Stub AI result (no expectations needed — only getContent() is stubbed)
        $aiResult = $this->createStub(ResultInterface::class);
        $aiResult->method('getContent')->willReturn($cannedBriefing);

        $this->agent
            ->expects(self::once())
            ->method('call')
            ->with(self::isInstanceOf(MessageBag::class))
            ->willReturn($aiResult);

        // Expect cache->set() to be called once with the briefing text
        $this->cacheManager
            ->expects(self::once())
            ->method('set')
            ->with(self::matchesRegularExpression('/^briefing\.acme\.\d{4}-\d{2}-\d{2}$/'), $cannedBriefing, 72_000);

        $result = $this->service->generate($tenantName);

        self::assertSame($cannedBriefing, $result);
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testReturnsCachedBriefingWithoutCallingAi(): void
    {
        $cached = "• Resumen en caché\n";

        $this->cacheManager->method('get')->willReturn($cached);

        // Neither DB nor AI should be touched
        $this->connection->expects(self::never())->method('fetchOne');
        $this->agent->expects(self::never())->method('call');

        $result = $this->service->generate('acme');

        self::assertSame($cached, $result);
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testReturnsDefaultMessageWhenAiCallFails(): void
    {
        // Cache miss
        $this->cacheManager->method('get')->willReturn(null);

        // Non-empty data
        $this->connection->method('fetchOne')->willReturn('500.00');
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['product' => 'Producto A', 'total' => '500.00'],
            ]);

        // AI throws
        $this->agent->method('call')->willThrowException(new \RuntimeException('OpenAI unavailable'));

        $result = $this->service->generate('acme');

        self::assertSame(DailyBriefingService::DEFAULT_MESSAGE, $result);
    }
}
