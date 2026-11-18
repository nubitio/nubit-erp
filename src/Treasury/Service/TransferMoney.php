<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\CurrencyMismatch;
use App\Treasury\Domain\DrawerWriteNotAvailable;
use App\Treasury\Domain\Leg;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Domain\TreasuryMovement;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** Moves money between two accounts as two legs that share a transfer id, in one transaction. */
final readonly class TransferMoney
{
    public function __construct(
        private TreasuryAccountRepository $accounts,
        private TreasuryMovementRepository $movements,
        private Connection $connection,
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
        if ($from->currency !== $to->currency) {
            throw CurrencyMismatch::between($from->currency, $to->currency);
        }
        foreach ([$from, $to] as $account) {
            if ($account->kind === AccountKind::CashRegister) {
                throw new DrawerWriteNotAvailable('Las transferencias desde o hacia una caja se habilitan en WP2.');
            }
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

        $this->connection->transactional(function () use ($leg, $from, $to, $value): void {
            $this->movements->append($leg(
                $from->id,
                Money::toString($value->negated()),
                Leg::Out,
                'Transferencia a ' . $to->name,
            ));
            $this->movements->append($leg(
                $to->id,
                Money::toString($value),
                Leg::In,
                'Transferencia desde ' . $from->name,
            ));
        });

        return $transferId;
    }
}
