<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\MoneyMovementResult;
use App\Treasury\Port\RecordsMoneyMovement;
use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * What other contexts get when they ask for {@see RecordsMoneyMovement}: the real recorder when the tenant has
 * the `treasury.accounts` capability on, and a no-op (null) when it is off, so payments behave exactly as before
 * Treasury until it is switched on.
 */
#[AsAlias(RecordsMoneyMovement::class)]
final readonly class TreasuryEnabledRecorder implements RecordsMoneyMovement
{
    public function __construct(
        private TreasuryMovementRecorder $inner,
        private TenantCapabilityResolver $capabilities,
    ) {}

    #[Override]
    public function record(MoneyMovementRequest $request): ?MoneyMovementResult
    {
        return $this->enabled() ? $this->inner->record($request) : null;
    }

    #[Override]
    public function reverse(
        MovementKey $original,
        MovementKey $reversal,
        DateTimeImmutable $occurredAt,
        ?int $userId,
        ?string $description = null,
    ): ?MoneyMovementResult {
        return $this->enabled()
            ? $this->inner->reverse($original, $reversal, $occurredAt, $userId, $description)
            : null;
    }

    private function enabled(): bool
    {
        return $this->capabilities->isEnabled(CapabilityKey::TreasuryAccounts);
    }
}
