<?php

declare(strict_types=1);

namespace App\AI\Briefing;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Nubit\Platform\Cache\CacheManager;
use Doctrine\DBAL\Connection;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Generates a daily AI-narrated morning briefing for a tenant.
 *
 * Gathers raw data from the tenant DB (sales, stock, SUNAT events, calendar),
 * formats a concise Spanish prompt, calls gpt-4o-mini via the existing ai.agent.default
 * service, and caches the result in Redis with a 20-hour TTL.
 *
 * Cache key pattern: briefing.{tenantName}.{Y-m-d}
 */
final readonly class DailyBriefingService
{
    private const int CACHE_TTL = 72_000; // 20 hours in seconds

    public const string DEFAULT_MESSAGE = 'Buenos días. No hay datos suficientes para generar el resumen de hoy.';

    public function __construct(
        private AgentInterface $agent,
        private Connection $connection,
        private CacheManager $cacheManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Generate (or return from cache) the daily briefing for the current tenant context.
     *
     * @param string $tenantName Used to build the cache key (already injected in TenantContext,
     *                           but passed explicitly so this service is context-independent).
     */
    public function generate(string $tenantName): string
    {
        $date = new DateTimeImmutable('now', new DateTimeZone('America/Lima'))->format('Y-m-d');
        $cacheKey = sprintf('briefing.%s.%s', $tenantName, $date);

        try {
            $cached = $this->cacheManager->get($cacheKey);
            if (\is_string($cached) && $cached !== '') {
                $this->logger->debug('DailyBriefingService: returning cached briefing', [
                    'tenant' => $tenantName,
                    'date' => $date,
                ]);

                return $cached;
            }
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->logger->warning('DailyBriefingService: cache read failed', ['exception' => $invalidArgumentException->getMessage()]);
        }

        $data = $this->gatherData($date);

        if ($this->isDataEmpty($data)) {
            return self::DEFAULT_MESSAGE;
        }

        $briefing = $this->callAi($data, $tenantName, $date);

        try {
            $this->cacheManager->set($cacheKey, $briefing, self::CACHE_TTL);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->logger->warning('DailyBriefingService: cache write failed', ['exception' => $invalidArgumentException->getMessage()]);
        }

        return $briefing;
    }

    // -------------------------------------------------------------------------
    // Data gathering
    // -------------------------------------------------------------------------

    /**
     * @return array{
     *   sales_total: float,
     *   sales_count: int,
     *   top_products: list<array{product: string, total: float}>,
     *   low_stock_count: int,
     *   sunat_rejections: int,
     *   is_holiday: bool,
     *   holiday_name: string|null,
     *   is_payday_week: bool,
     * }
     */
    private function gatherData(string $date): array
    {
        $yesterday = new DateTimeImmutable($date, new DateTimeZone('America/Lima'))
            ->modify('-1 day')
            ->format('Y-m-d');

        return [
            'sales_total'      => $this->fetchYesterdaySalesTotal($yesterday),
            'sales_count'      => $this->fetchYesterdaySalesCount($yesterday),
            'top_products'     => $this->fetchTopProducts($yesterday),
            'low_stock_count'  => $this->fetchLowStockCount(),
            'sunat_rejections' => $this->fetchSunatRejections(),
            'is_holiday'       => $this->fetchIsHoliday($date),
            'holiday_name'     => $this->fetchHolidayName($date),
            'is_payday_week'   => $this->isPaydayWeek($date),
        ];
    }

    private function fetchYesterdaySalesTotal(string $yesterday): float
    {
        try {
            $value = $this->connection->fetchOne(
                "SELECT COALESCE(SUM(bds.net_sales), 0)
                 FROM branch_daily_summary bds
                 WHERE bds.summary_date = ?",
                [$yesterday],
            );

            return (float) $value;
        } catch (Throwable $throwable) {
            $this->logger->debug('DailyBriefingService: sales total query failed', ['exception' => $throwable->getMessage()]);

            return 0.0;
        }
    }

    private function fetchYesterdaySalesCount(string $yesterday): int
    {
        try {
            $value = $this->connection->fetchOne(
                "SELECT COALESCE(SUM(bds.sales_count), 0)
                 FROM branch_daily_summary bds
                 WHERE bds.summary_date = ?",
                [$yesterday],
            );

            return (int) $value;
        } catch (Throwable $throwable) {
            $this->logger->debug('DailyBriefingService: sales count query failed', ['exception' => $throwable->getMessage()]);

            return 0;
        }
    }

    /**
     * @return list<array{product: string, total: float}>
     */
    private function fetchTopProducts(string $yesterday): array
    {
        try {
            /** @var array<int, array<string, mixed>> $rows */
            $rows = $this->connection->fetchAllAssociative(
                "SELECT COALESCE(p.descripcion, 'Sin producto') AS product,
                        COALESCE(SUM(pds.net_sales), 0) AS total
                 FROM product_daily_summary pds
                 LEFT JOIN producto p ON pds.product_id = p.producto_id
                 WHERE pds.summary_date = ?
                 GROUP BY pds.product_id, p.descripcion
                 ORDER BY total DESC
                 LIMIT 3",
                [$yesterday],
            );

            return array_map(
                static fn (array $row): array => [
                    'product' => (string) $row['product'],
                    'total'   => (float) $row['total'],
                ],
                $rows
            );
        } catch (Throwable $throwable) {
            $this->logger->debug('DailyBriefingService: top products query failed', ['exception' => $throwable->getMessage()]);

            return [];
        }
    }

    private function fetchLowStockCount(): int
    {
        try {
            $value = $this->connection->fetchOne(
                "SELECT COUNT(*)
                 FROM product_stock ps
                 WHERE ps.min_stock > 0
                   AND ps.stock <= ps.min_stock",
            );

            return (int) $value;
        } catch (Throwable $throwable) {
            $this->logger->debug('DailyBriefingService: low stock count query failed', ['exception' => $throwable->getMessage()]);

            return 0;
        }
    }

    private function fetchSunatRejections(): int
    {
        try {
            $since = new DateTimeImmutable('now', new DateTimeZone('UTC'))
                ->modify('-24 hours')
                ->format('Y-m-d H:i:s');

            $value = $this->connection->fetchOne(
                "SELECT COUNT(*)
                 FROM tax_document_event
                 WHERE status = 'error'
                   AND created_at >= ?",
                [$since],
            );

            return (int) $value;
        } catch (Throwable $throwable) {
            $this->logger->debug('DailyBriefingService: SUNAT rejections query failed', ['exception' => $throwable->getMessage()]);

            return 0;
        }
    }

    private function fetchIsHoliday(string $date): bool
    {
        try {
            $value = $this->connection->fetchOne(
                "SELECT COALESCE(holiday, 0) FROM time_dimension WHERE `date` = ?",
                [$date],
            );

            return (bool) $value;
        } catch (Throwable) {
            return false;
        }
    }

    private function fetchHolidayName(string $date): ?string
    {
        try {
            $value = $this->connection->fetchOne(
                "SELECT `event` FROM time_dimension WHERE `date` = ? AND holiday = 1",
                [$date],
            );

            if ($value === false || $value === null) {
                return null;
            }

            $str = (string) $value;

            return $str !== '' ? $str : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isPaydayWeek(string $date): bool
    {
        $day = (int) new DateTimeImmutable($date)->format('j');

        // Typical Peruvian payday windows: last week of month (day ≥ 25) or first week (day ≤ 7)
        return $day >= 25 || $day <= 7;
    }

    // -------------------------------------------------------------------------
    // AI call
    // -------------------------------------------------------------------------

    /**
     * @param array{
     *   sales_total: float,
     *   sales_count: int,
     *   top_products: list<array{product: string, total: float}>,
     *   low_stock_count: int,
     *   sunat_rejections: int,
     *   is_holiday: bool,
     *   holiday_name: string|null,
     *   is_payday_week: bool,
     * } $data
     */
    private function callAi(array $data, string $tenantName, string $date): string
    {
        $prompt = $this->buildPrompt($data, $tenantName, $date);

        try {
            $messageBag = new MessageBag(
                Message::forSystem(
                    'Eres un asistente de negocios conciso para una empresa peruana de facturación electrónica. '
                    . 'Responde siempre en español, en formato de lista con viñetas. '
                    . 'Máximo 150 palabras. Resalta alertas urgentes con ⚠️.'
                ),
                Message::ofUser($prompt),
            );

            $result = $this->agent->call($messageBag, ['model' => 'gpt-4o-mini']);
            $content = $result->getContent();

            if (\is_string($content) && $content !== '') {
                return trim($content);
            }

            $this->logger->warning('DailyBriefingService: AI returned non-string content', [
                'tenant' => $tenantName,
                'type'   => \gettype($content),
            ]);

            return self::DEFAULT_MESSAGE;
        } catch (Throwable $throwable) {
            $this->logger->error('DailyBriefingService: AI call failed', [
                'tenant'    => $tenantName,
                'exception' => $throwable->getMessage(),
            ]);

            return self::DEFAULT_MESSAGE;
        }
    }

    /**
     * @param array{
     *   sales_total: float,
     *   sales_count: int,
     *   top_products: list<array{product: string, total: float}>,
     *   low_stock_count: int,
     *   sunat_rejections: int,
     *   is_holiday: bool,
     *   holiday_name: string|null,
     *   is_payday_week: bool,
     * } $data
     */
    public function buildPrompt(array $data, string $tenantName, string $date): string
    {
        $topProductsText = 'No hay datos de productos.';
        if ($data['top_products'] !== []) {
            $lines = [];
            foreach ($data['top_products'] as $i => $p) {
                $lines[] = sprintf('%d. %s — S/ %.2f', $i + 1, $p['product'], $p['total']);
            }

            $topProductsText = implode('; ', $lines);
        }

        $calendarNote = '';
        if ($data['is_holiday']) {
            $calendarNote = sprintf('HOY ES FERIADO: %s. ', $data['holiday_name'] ?? 'Feriado nacional');
        }

        if ($data['is_payday_week']) {
            $calendarNote .= 'Estamos en semana de pagos (quincena/fin de mes). ';
        }

        return sprintf(
            <<<'PROMPT'
            Genera el resumen matutino del negocio para la empresa "%s" correspondiente al día %s.

            DATOS DE AYER:
            - Ventas totales: S/ %.2f (%d transacciones)
            - Top 3 productos por ingresos: %s
            - Productos por debajo del stock mínimo: %d
            - Rechazos SUNAT en las últimas 24 h: %d

            CONTEXTO DE CALENDARIO:
            %s

            INSTRUCCIONES:
            - Escribe exactamente 5 puntos en formato de lista con viñetas (•).
            - Máximo 150 palabras en total.
            - Si hay productos bajo stock mínimo o rechazos SUNAT, márcalos con ⚠️ y ponlos primero.
            - Tono profesional y motivador, como un asistente de negocios.
            - No inventes datos que no aparezcan arriba.
            PROMPT,
            $tenantName,
            $date,
            $data['sales_total'],
            $data['sales_count'],
            $topProductsText,
            $data['low_stock_count'],
            $data['sunat_rejections'],
            $calendarNote !== '' ? $calendarNote : 'Día laboral normal.',
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array{
     *   sales_total: float,
     *   sales_count: int,
     *   top_products: list<array{product: string, total: float}>,
     *   low_stock_count: int,
     *   sunat_rejections: int,
     *   is_holiday: bool,
     *   holiday_name: string|null,
     *   is_payday_week: bool,
     * } $data
     */
    private function isDataEmpty(array $data): bool
    {
        return $data['sales_total'] === 0.0
            && $data['sales_count'] === 0
            && $data['top_products'] === []
            && $data['low_stock_count'] === 0
            && $data['sunat_rejections'] === 0;
    }
}
