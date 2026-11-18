<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\CurrencyMismatch;
use App\Treasury\Domain\DrawerWriteNotAvailable;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Domain\TreasuryMovement;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\MoneyMovementResult;
use App\Treasury\Port\RecordsMoneyMovement;
use App\Treasury\Repository\TreasuryMovementRepository;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class TreasuryMovementRecorder implements RecordsMoneyMovement
{
    public function __construct(
        private MoneyAccountResolver $resolver,
        private TreasuryMovementRepository $movements,
    ) {}

    #[Override]
    public function record(MoneyMovementRequest $request): MoneyMovementResult
    {
        $amount = Money::money($request->amount);
        if ($amount->isLessThanOrEqualTo(0)) {
            throw new TreasuryException('El monto de un movimiento debe ser mayor a 0.');
        }

        ['account' => $account, 'warning' => $warning] = $this->resolver->resolve($request);

        if ($account->currency !== $request->currency) {
            throw CurrencyMismatch::between($account->currency, $request->currency);
        }
        if ($account->kind === AccountKind::CashRegister) {
            throw new DrawerWriteNotAvailable(
                'Registrar en una caja se habilita junto con los pagos a proveedores (WP2).',
            );
        }

        $existing = $this->movements->findByKey($request->key);
        if ($existing !== null) {
            return new MoneyMovementResult($existing->id, $existing->accountId, $account->kind, true);
        }

        $signed = $request->direction->sign() < 0 ? $amount->negated() : $amount;
        $movement = new TreasuryMovement(
            id: Uuid::v7()->toRfc4122(),
            accountId: $account->id,
            signedAmount: Money::toString($signed),
            currency: $request->currency,
            occurredAt: $request->occurredAt,
            key: $request->key,
            party: $request->party,
            reference: $request->reference,
            description: $request->description,
            transferId: null,
            correctsMovementId: null,
            origin: $request->origin,
            createdBy: $request->userId,
        );

        // A concurrent writer can win the unique key between the read above and this insert.
        if (!$this->movements->append($movement)) {
            $winner = $this->movements->findByKey($request->key);

            return new MoneyMovementResult($winner->id ?? $movement->id, $account->id, $account->kind, true);
        }

        return new MoneyMovementResult($movement->id, $account->id, $account->kind, false, $warning);
    }
}
