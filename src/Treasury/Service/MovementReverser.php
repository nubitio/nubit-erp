<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\CashRegister\Contract\AppendsCashMovement;
use App\CashRegister\Contract\CashMovementEntry;
use App\CashRegister\Contract\CashMovementRejected;
use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Domain\TreasuryMovement;
use App\Treasury\Port\MoneyMovementResult;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Undoes a movement with a correcting one on the same account, so a reversal never lands somewhere the
 * original did not (the mapping may have changed since). A ledger movement gets an opposite row pointing at
 * it; a drawer movement gets the opposite cash movement in the user's open session.
 */
final readonly class MovementReverser
{
    public function __construct(
        private TreasuryMovementRepository $movements,
        private TreasuryAccountRepository $accounts,
        private AppendsCashMovement $cash,
        private DrawerReader $drawers,
    ) {}

    public function reverse(
        MovementKey $original,
        MovementKey $reversal,
        DateTimeImmutable $occurredAt,
        ?int $userId,
        ?string $description,
    ): ?MoneyMovementResult {
        $ledger = $this->movements->findByKey($original);
        if ($ledger !== null) {
            return $this->reverseLedger($ledger, $reversal, $occurredAt, $userId, $description);
        }

        $drawerRef = $this->cash->find($original->asUuid());

        return $drawerRef === null
            ? null
            : $this->reverseDrawer(
                $drawerRef->isInflow,
                $drawerRef->amount,
                $reversal,
                $occurredAt,
                $userId,
                $description,
            );
    }

    private function reverseLedger(
        TreasuryMovement $original,
        MovementKey $reversal,
        DateTimeImmutable $occurredAt,
        ?int $userId,
        ?string $description,
    ): MoneyMovementResult {
        $account = $this->accounts->find($original->accountId) ?? throw new TreasuryException(
            'La cuenta del movimiento original ya no existe.',
        );
        $existing = $this->movements->findByKey($reversal);
        if ($existing !== null) {
            return new MoneyMovementResult($existing->id, $existing->accountId, $account->kind, true);
        }

        $movement = new TreasuryMovement(
            id: Uuid::v7()->toRfc4122(),
            accountId: $original->accountId,
            signedAmount: Money::toString(Money::money($original->signedAmount)->negated()),
            currency: $original->currency,
            occurredAt: $occurredAt,
            key: $reversal,
            party: $original->party,
            reference: $original->reference,
            description: $description ?? 'Reversión',
            transferId: null,
            correctsMovementId: $original->id,
            origin: 'live',
            createdBy: $userId,
        );
        if (!$this->movements->append($movement)) {
            $winner = $this->movements->findByKey($reversal);

            return new MoneyMovementResult($winner->id ?? $movement->id, $account->id, $account->kind, true);
        }

        return new MoneyMovementResult($movement->id, $account->id, $account->kind, false);
    }

    private function reverseDrawer(
        bool $wasInflow,
        string $amount,
        MovementKey $reversal,
        DateTimeImmutable $occurredAt,
        ?int $userId,
        ?string $description,
    ): MoneyMovementResult {
        if ($userId === null) {
            throw new TreasuryException('Reversar un movimiento de caja necesita el usuario que lo registra.');
        }
        $session = $this->drawers->openSessionFor($userId);
        if ($session === null) {
            throw new TreasuryException(
                'Para reversar un pago en efectivo necesitas una caja abierta: el dinero vuelve a esa caja.',
            );
        }
        $account = $this->accounts->findByCashRegister($session['registerId']);
        if ($account === null || $account->kind !== AccountKind::CashRegister) {
            throw new TreasuryException(
                'La caja abierta no tiene cuenta de dinero; ejecuta la configuración inicial de cuentas.',
            );
        }

        $direction = $wasInflow ? Direction::Out : Direction::In;
        try {
            $ref = $this->cash->append(new CashMovementEntry(
                sessionId: $session['sessionId'],
                isInflow: $direction === Direction::In,
                amount: $amount,
                categoryCode: DrawerRecorder::categoryFor($reversal->source, $direction),
                description: $description ?? 'Reversión',
                userId: $userId,
                idempotencyKey: $reversal->asUuid(),
                occurredAt: $occurredAt,
            ));
        } catch (CashMovementRejected $e) {
            throw new TreasuryException($e->getMessage(), 0, $e);
        }

        return new MoneyMovementResult((string) $ref->id, $account->id, AccountKind::CashRegister, $ref->duplicate);
    }
}
