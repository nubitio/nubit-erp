<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementSource;
use DateTimeImmutable;

/** A payment that has no Treasury movement yet, with what is needed to record it. */
final readonly class MissingMovement
{
    public function __construct(
        public MovementSource $source,
        public string $sourceId,
        public Direction $direction,
        public string $amount,
        public string $currency,
        public string $branchId,
        public int $paymentMethodId,
        public DateTimeImmutable $occurredAt,
        public ?string $partyType,
        public ?string $partyId,
        public ?string $reference,
        public string $description,
    ) {}
}
