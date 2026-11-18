<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\Party;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use DateTimeImmutable;

/**
 * Records the movements {@see TreasuryGaps} finds. Idempotent: it writes through the same keys as the live
 * path, so running it twice, or running it while payments keep arriving, never duplicates a movement. Rows it
 * writes are marked `origin = backfill`.
 */
final readonly class TreasuryBackfill
{
    public function __construct(
        private TreasuryGaps $gaps,
        private TreasuryMovementRecorder $recorder,
    ) {}

    /** @return array{found: int, recorded: int, suspense: int, failed: list<string>} */
    public function run(DateTimeImmutable $from, bool $dryRun): array
    {
        $missing = $this->gaps->missing($from);
        $recorded = 0;
        $suspense = 0;
        $failed = [];

        if ($dryRun) {
            return ['found' => count($missing), 'recorded' => 0, 'suspense' => 0, 'failed' => []];
        }

        foreach ($missing as $item) {
            try {
                $result = $this->recorder->record(new MoneyMovementRequest(
                    key: new MovementKey($item->source, $item->sourceId),
                    direction: $item->direction,
                    amount: $item->amount,
                    currency: $item->currency,
                    branchId: $item->branchId,
                    occurredAt: $item->occurredAt,
                    paymentMethodId: $item->paymentMethodId,
                    party: $item->partyType === null || $item->partyId === null
                        ? null
                        : new Party($item->partyType, $item->partyId),
                    reference: $item->reference,
                    description: $item->description,
                    origin: 'backfill',
                ));
            } catch (TreasuryException $e) {
                $failed[] = sprintf('%s %s: %s', $item->source->value, $item->sourceId, $e->getMessage());
                continue;
            }
            if (!$result->duplicate) {
                ++$recorded;
            }
            if ($result->warning !== null) {
                ++$suspense;
            }
        }

        return ['found' => count($missing), 'recorded' => $recorded, 'suspense' => $suspense, 'failed' => $failed];
    }
}
