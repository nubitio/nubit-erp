<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

use App\Efact\Domain\Money;
use DateTimeImmutable;

/** One immutable row of the ledger. The amount is signed: money in is positive, money out is negative. */
final readonly class TreasuryMovement
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $signedAmount,
        public string $currency,
        public DateTimeImmutable $occurredAt,
        public MovementKey $key,
        public ?Party $party,
        public ?string $reference,
        public ?string $description,
        public ?string $transferId,
        public ?string $correctsMovementId,
        public string $origin,
        public ?int $createdBy,
    ) {}

    public function direction(): Direction
    {
        return Money::money($this->signedAmount)->isNegative() ? Direction::Out : Direction::In;
    }
}
