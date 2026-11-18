<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\CashRegister\Contract\AppendsCashMovement;
use App\CashRegister\Contract\CashMovementEntry;
use App\CashRegister\Contract\CashMovementRejected;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\MoneyMovementResult;

/**
 * Records into a cash drawer. The drawer keeps its own ledger, so this appends to `cash_movements` through
 * the cash register's contract, inside the user's open session, which is what makes the arqueo see it.
 */
final readonly class DrawerRecorder
{
    public function __construct(
        private AppendsCashMovement $cash,
        private DrawerReader $drawers,
    ) {}

    public function record(
        MoneyMovementRequest $request,
        TreasuryAccount $account,
        ?string $warning,
    ): MoneyMovementResult {
        $sessionId = $this->sessionOn($account, $request->userId);

        try {
            $ref = $this->cash->append(new CashMovementEntry(
                sessionId: $sessionId,
                isInflow: $request->direction === Direction::In,
                amount: $request->amount,
                categoryCode: self::categoryFor($request->key->source, $request->direction),
                description: $request->description ?? 'Movimiento de tesorería',
                userId: (int) $request->userId,
                idempotencyKey: $request->key->asUuid(),
                paymentMethodId: $request->paymentMethodId,
                occurredAt: $request->occurredAt,
            ));
        } catch (CashMovementRejected $e) {
            throw new TreasuryException($e->getMessage(), 0, $e);
        }

        return new MoneyMovementResult(
            (string) $ref->id,
            $account->id,
            AccountKind::CashRegister,
            $ref->duplicate,
            $warning,
        );
    }

    /** The open session of the user, which must be on the register of the account being written. */
    public function sessionOn(TreasuryAccount $account, ?int $userId): int
    {
        if ($userId === null) {
            throw new TreasuryException('Un movimiento en efectivo necesita el usuario que lo registra.');
        }
        $session = $this->drawers->openSessionFor($userId);
        if ($session === null) {
            throw new TreasuryException('No tienes una caja abierta; abre una sesión de caja para registrar efectivo.');
        }
        if ($session['registerId'] !== $account->cashRegisterId) {
            throw new TreasuryException('Tu sesión de caja abierta es de otra caja distinta a la cuenta elegida.');
        }

        return $session['sessionId'];
    }

    /** Cash category per kind of movement; sources that do not touch a drawer through Treasury have none. */
    public static function categoryFor(MovementSource $source, Direction $direction): string
    {
        return match (true) {
            $source === MovementSource::SupplierPayment && $direction === Direction::Out => 'supplier_payment',
            $source === MovementSource::SupplierPaymentReversal && $direction === Direction::In
                => 'supplier_payment_reversal',
            default => throw new TreasuryException(sprintf('«%s» todavía no se registra en una caja.', $source->value)),
        };
    }
}
