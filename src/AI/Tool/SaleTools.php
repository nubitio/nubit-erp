<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Dto\Assistant\RecentSaleDto;
use App\Dto\Assistant\SalesSummaryDto;
use App\Repository\SaleRepository;
use DateInterval;
use DateTimeImmutable;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sales_summary',
    description: 'Sales summary for a date range (defaults to last 7 days).',
    method: 'salesSummary',
)]
#[AsTool(
    name: 'recent_sales',
    description: 'Latest sales with total amount and identifiers.',
    method: 'recentSales',
)]
final readonly class SaleTools
{
    public function __construct(
        private SaleRepository $saleRepository,
    ) {
    }

    public function salesSummary(?string $desde = null, ?string $hasta = null): SalesSummaryDto
    {
        $to = $this->parseDate($hasta) ?? new DateTimeImmutable('now');
        $from = $this->parseDate($desde) ?? $to->sub(new DateInterval('P7D'));

        $summary = $this->saleRepository->getSummary($from, $to);

        return $summary;
    }

    /**
     * @return RecentSaleDto[]
     */
    public function recentSales(int $limit = 5): array
    {
        return $this->saleRepository->getRecentSales($limit);
    }

    private function parseDate(?string $date): ?DateTimeImmutable
    {
        if (!$date) {
            return null;
        }

        try {
            return new DateTimeImmutable($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
