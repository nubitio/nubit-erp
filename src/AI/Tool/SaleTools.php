<?php

declare(strict_types=1);

namespace App\AI\Tool;

use Throwable;
use App\Reporting\Entity\TimeDimension;
use DateTimeInterface;
use App\Dto\Assistant\RecentSaleDto;
use App\Sales\Repository\SalesAnalyticsRepository;
use App\Reporting\Repository\TimeDimensionRepository;
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
        private SalesAnalyticsRepository $saleRepository,
        private TimeDimensionRepository $timeDimensionRepository,
    ) {
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     totalSales: int,
     *     totalAmount: float,
     *     averageTicket: float,
     *     temporalContext: array{
     *         isHoliday: bool,
     *         holidayNames: list<string>,
     *         isQuincenaWeek: bool,
     *         periodLabel: string,
     *         workingDays: int
     *     }
     * }
     */
    public function salesSummary(?string $desde = null, ?string $hasta = null): array
    {
        $to = $this->parseDate($hasta) ?? new DateTimeImmutable('now');
        $from = $this->parseDate($desde) ?? $to->sub(new DateInterval('P7D'));

        $summary = $this->saleRepository->getSummary($from, $to);
        $temporalContext = $this->buildTemporalContext($from, $to);

        return [
            'from' => $summary->from,
            'to' => $summary->to,
            'totalSales' => $summary->totalSales,
            'totalAmount' => $summary->totalAmount,
            'averageTicket' => $summary->averageTicket,
            'temporalContext' => $temporalContext,
        ];
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
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     isHoliday: bool,
     *     holidayNames: list<string>,
     *     isQuincenaWeek: bool,
     *     periodLabel: string,
     *     workingDays: int
     * }
     */
    private function buildTemporalContext(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $fromId = (int) $from->format('Ymd');
        $toId = (int) $to->format('Ymd');

        /** @var list<TimeDimension> $dimensions */
        $dimensions = $this->timeDimensionRepository->createQueryBuilder('td')
            ->where('td.id >= :fromId')
            ->andWhere('td.id <= :toId')
            ->setParameter('fromId', $fromId)
            ->setParameter('toId', $toId)
            ->orderBy('td.id', 'ASC')
            ->getQuery()
            ->getResult();

        if ($dimensions === []) {
            return [
                'isHoliday' => false,
                'holidayNames' => [],
                'isQuincenaWeek' => false,
                'periodLabel' => 'Sin datos de calendario',
                'workingDays' => 0,
            ];
        }

        $isHoliday = false;
        /** @var list<string> $holidayNames */
        $holidayNames = [];
        $workingDays = 0;
        $isQuincenaWeek = false;

        foreach ($dimensions as $dim) {
            $holiday = $dim->isHoliday() ?? false;
            $weekend = $dim->isWeekend() ?? false;
            $day = $dim->getDay();

            if ($holiday) {
                $isHoliday = true;
                $eventName = $dim->getEvent();
                if ($eventName !== null && $eventName !== '') {
                    $holidayNames[] = $eventName;
                }
            }

            if (!$holiday && !$weekend) {
                ++$workingDays;
            }

            if ($day !== null && ($day === 15 || $this->isLastDayOfMonth($dim))) {
                $isQuincenaWeek = true;
            }
        }

        $periodLabel = $this->buildPeriodLabel($from, $to, $dimensions);

        return [
            'isHoliday' => $isHoliday,
            'holidayNames' => array_values(array_unique($holidayNames)),
            'isQuincenaWeek' => $isQuincenaWeek,
            'periodLabel' => $periodLabel,
            'workingDays' => $workingDays,
        ];
    }

    private function isLastDayOfMonth(TimeDimension $dim): bool
    {
        $date = $dim->getDate();
        if (!$date instanceof DateTimeInterface) {
            return false;
        }

        $dayOfMonth = (int) $date->format('j');
        $daysInMonth = (int) $date->format('t');

        return $dayOfMonth === $daysInMonth;
    }

    /**
     * @param list<TimeDimension> $dimensions
     */
    private function buildPeriodLabel(DateTimeImmutable $from, DateTimeImmutable $to, array $dimensions): string
    {
        $isSingleDay = $from->format('Y-m-d') === $to->format('Y-m-d');

        // Determine if the range is within a single month
        $singleMonth = $from->format('Y-m') === $to->format('Y-m');

        // Grab the first dimension for week/month name
        $first = $dimensions[0] ?? null;
        $week = $first?->getWeek();
        $monthName = $first?->getMonthName();
        $year = $from->format('Y');
        $hasHoliday = array_any($dimensions, fn ($dim): bool => $dim->isHoliday() === true);

        $qualifier = $hasHoliday ? 'semana con feriado' : 'semana normal';

        if ($isSingleDay) {
            $dayName = $first?->getDayName() ?? '';
            $day = $from->format('j');
            $month = $monthName ?? $from->format('F');

            return sprintf('%s %s de %s %s', $dayName, $day, $month, $year);
        }

        if ($singleMonth && $week !== null && $monthName !== null) {
            return sprintf('Semana %d de %s %s (%s)', $week, $monthName, $year, $qualifier);
        }

        return sprintf(
            '%s al %s (%s)',
            $from->format('d/m/Y'),
            $to->format('d/m/Y'),
            $qualifier
        );
    }
}
