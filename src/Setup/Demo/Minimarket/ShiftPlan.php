<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTimeImmutable;

/** One cashier's turn at the till: when it opened and closed, what was sold and how the count came out. */
final readonly class ShiftPlan
{
    /**
     * @param list<SalePlan> $sales
     * @param list<array{at: DateTimeImmutable, amount: float, category: string, description: string}> $outflows
     * @param float|null $difference counted minus expected at closing; null while the shift is still open
     */
    public function __construct(
        public string $cashier,
        public DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
        public float $openingAmount,
        public array $sales,
        public array $outflows,
        public ?float $difference,
        public ?string $closeNote = null,
    ) {}

    public function isOpen(): bool
    {
        return $this->closesAt === null;
    }
}
