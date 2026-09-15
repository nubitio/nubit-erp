<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use DateTimeImmutable;

/**
 * Buckets a customer's outstanding receivable balance by age at a cut-off date
 * (#366): 0–30 / 31–60 / 61–90 / 90+ days since the originating credit sale.
 *
 * @phpstan-import-type ChargeRow from StatementAssembler
 */
final readonly class AgingCalculator
{
    public const string BUCKET_CURRENT = '0-30';
    public const string BUCKET_31_60 = '31-60';
    public const string BUCKET_61_90 = '61-90';
    public const string BUCKET_90_PLUS = '90+';

    /**
     * @param list<ChargeRow> $charges
     *
     * @return array<string, string> bucket label => amount
     */
    public function bucketed(array $charges, string $dateTo): array
    {
        $buckets = [
            self::BUCKET_CURRENT => BigDecimal::zero(),
            self::BUCKET_31_60 => BigDecimal::zero(),
            self::BUCKET_61_90 => BigDecimal::zero(),
            self::BUCKET_90_PLUS => BigDecimal::zero(),
        ];

        $cutoff = new DateTimeImmutable($dateTo);
        foreach ($charges as $charge) {
            $due = Money::of($charge['balance_due']);
            if ($due->isLessThanOrEqualTo(0) || $charge['date'] > $dateTo) {
                continue;
            }

            $label = $this->label((int) $cutoff->diff(new DateTimeImmutable($charge['date']))->days);
            $buckets[$label] = $buckets[$label]->plus($due);
        }

        return array_map(Money::toString(...), $buckets);
    }

    private function label(int $days): string
    {
        return match (true) {
            $days <= 30 => self::BUCKET_CURRENT,
            $days <= 60 => self::BUCKET_31_60,
            $days <= 90 => self::BUCKET_61_90,
            default => self::BUCKET_90_PLUS,
        };
    }
}
