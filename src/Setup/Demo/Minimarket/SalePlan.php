<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTimeImmutable;

/** One sale the demo will record, with everything that happened to it afterwards. */
final readonly class SalePlan
{
    public const string NORMAL = 'normal';
    public const string VOIDED = 'voided';
    public const string CREDIT_NOTE = 'credit_note';

    /**
     * @param list<array{code: string, qty: int}> $lines
     * @param self::NORMAL|self::VOIDED|self::CREDIT_NOTE $outcome
     * @param 'accepted'|'pending'|'rejected' $sunat
     */
    public function __construct(
        public string $cashier,
        public DateTimeImmutable $at,
        public ?string $customerKey,
        public array $lines,
        public string $payment,
        public float $discountPercent = 0.0,
        public ?string $discountReason = null,
        public string $outcome = self::NORMAL,
        public ?string $outcomeReason = null,
        public ?DateTimeImmutable $outcomeAt = null,
        public string $sunat = 'accepted',
        public int $creditDays = 0,
    ) {}

    public function isCredit(): bool
    {
        return $this->payment === 'Credito';
    }

    public function isFactura(): bool
    {
        return in_array($this->customerKey, ['restaurante', 'panaderia'], true);
    }

    /** Cash that really stayed in the drawer: a sale paid in cash and not undone. */
    public function keepsCash(): bool
    {
        return $this->payment === 'Efectivo' && $this->outcome === self::NORMAL;
    }
}
