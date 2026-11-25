<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\CurrencyMismatch;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\Leg;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Domain\TreasuryMovement;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Moves money between two accounts as two legs that share a transfer id, in one transaction. A drawer is one of
 * the two sides at most: its leg is written in the cash register's own ledger, inside the user's open session.
 */
final readonly class TransferMoney
{
    public function __construct(
        private TreasuryAccountRepository $accounts,
        private TreasuryMovementRepository $movements,
        private Connection $connection,
        private DrawerRecorder $drawer,
    ) {}

    /** @return string the transfer id */
    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        string $amount,
        DateTimeImmutable $occurredAt,
        ?string $reference = null,
        ?int $userId = null,
    ): string {
        $value = Money::money($amount);
        if ($value->isLessThanOrEqualTo(0)) {
            throw new TreasuryException('El monto de una transferencia debe ser mayor a 0.');
        }
        if ($fromAccountId === $toAccountId) {
            throw new TreasuryException('El origen y el destino de una transferencia deben ser cuentas distintas.');
        }

        $from = $this->accounts->find($fromAccountId);
        $to = $this->accounts->find($toAccountId);
        if ($from === null || $to === null || !$from->active || !$to->active) {
            throw new TreasuryException('Las cuentas de la transferencia deben existir y estar activas.');
        }
        if (in_array(AccountKind::Clearing, [$from->kind, $to->kind], strict: true)) {
            throw new TreasuryException('Los fondos por liquidar requieren una liquidación de pasarela.');
        }
        if ($from->currency !== $to->currency) {
            throw CurrencyMismatch::between($from->currency, $to->currency);
        }
        if ($from->kind === AccountKind::CashRegister && $to->kind === AccountKind::CashRegister) {
            throw new TreasuryException(
                'Para mover efectivo entre dos cajas registra un retiro en una y un ingreso en la otra.',
            );
        }

        $transferId = Uuid::v7()->toRfc4122();
        $leg = fn(
            string $accountId,
            string $signed,
            Leg $leg,
            ?string $description,
        ): TreasuryMovement => new TreasuryMovement(
            id: Uuid::v7()->toRfc4122(),
            accountId: $accountId,
            signedAmount: $signed,
            currency: $from->currency,
            occurredAt: $occurredAt,
            key: new MovementKey(MovementSource::Transfer, $transferId, $leg),
            party: null,
            reference: $reference,
            description: $description,
            transferId: $transferId,
            correctsMovementId: null,
            origin: 'live',
            createdBy: $userId,
        );

        $legs = [
            [$from, Leg::Out, Direction::Out, 'Transferencia a ' . $to->name],
            [$to, Leg::In, Direction::In, 'Transferencia desde ' . $from->name],
        ];
        $this->connection->transactional(function () use (
            $legs,
            $leg,
            $value,
            $transferId,
            $occurredAt,
            $reference,
            $userId,
        ): void {
            // A drawer is written first: it needs the user's open session on that register and can refuse.
            foreach ($legs as [$account, $side, $direction, $description]) {
                if ($account->kind === AccountKind::CashRegister) {
                    $this->writeDrawer(
                        $account,
                        $side,
                        $direction,
                        $transferId,
                        $value,
                        $occurredAt,
                        $reference,
                        $description,
                        $userId,
                    );
                }
            }
            foreach ($legs as [$account, $side, $direction, $description]) {
                if ($account->kind !== AccountKind::CashRegister) {
                    $signed = $direction === Direction::Out ? $value->negated() : $value;
                    $this->movements->append($leg($account->id, Money::toString($signed), $side, $description));
                }
            }
        });

        return $transferId;
    }

    /** A drawer leg goes through the cash register's own ledger, inside the user's open session. */
    private function writeDrawer(
        TreasuryAccount $account,
        Leg $leg,
        Direction $direction,
        string $transferId,
        BigDecimal $amount,
        DateTimeImmutable $occurredAt,
        ?string $reference,
        string $description,
        ?int $userId,
    ): void {
        $this->drawer->record(
            new MoneyMovementRequest(
                key: new MovementKey(MovementSource::Transfer, $transferId, $leg),
                direction: $direction,
                amount: Money::toString($amount),
                currency: $account->currency,
                branchId: (string) $account->branchId,
                occurredAt: $occurredAt,
                isCash: true,
                userId: $userId,
                reference: $reference,
                description: $description,
                accountId: $account->id,
            ),
            $account,
            null,
        );
    }
}
