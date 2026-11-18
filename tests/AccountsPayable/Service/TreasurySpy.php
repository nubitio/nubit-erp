<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Service;

use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\MoneyMovementResult;
use App\Treasury\Port\RecordsMoneyMovement;
use DateTimeImmutable;

/** Remembers what a payment asked Treasury to record or reverse, and can be told to refuse. */
final class TreasurySpy implements RecordsMoneyMovement
{
    /** @var list<MoneyMovementRequest> */
    public array $recorded = [];

    /** @var list<array{0: MovementKey, 1: MovementKey, 2: ?int}> */
    public array $reversed = [];

    public ?TreasuryException $failWith = null;

    public function record(MoneyMovementRequest $request): ?MoneyMovementResult
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
        $this->recorded[] = $request;

        return null;
    }

    public function reverse(
        MovementKey $original,
        MovementKey $reversal,
        DateTimeImmutable $occurredAt,
        ?int $userId,
        ?string $description = null,
    ): ?MoneyMovementResult {
        $this->reversed[] = [$original, $reversal, $userId];

        return null;
    }
}
