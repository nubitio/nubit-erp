<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use App\CashRegister\Contract\AppendsCashMovement;
use App\CashRegister\Contract\CashMovementEntry;
use App\CashRegister\Contract\CashMovementRef;
use App\CashRegister\Contract\CashMovementRejected;
use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Override;

final readonly class CashMovementAppender implements AppendsCashMovement
{
    public function __construct(
        private Connection $connection,
    ) {}

    #[Override]
    public function append(CashMovementEntry $entry): CashMovementRef
    {
        $existing = $this->find($entry->idempotencyKey);
        if ($existing !== null) {
            return new CashMovementRef($existing->id, $existing->isInflow, $existing->amount, true);
        }

        $amount = Money::money($entry->amount);
        if ($amount->isLessThanOrEqualTo(0)) {
            throw new CashMovementRejected('El monto del movimiento de caja debe ser mayor a 0.');
        }

        $lock = $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
        $status = $this->connection->fetchOne('SELECT status FROM cash_sessions WHERE id = :id' . $lock, [
            'id' => $entry->sessionId,
        ]);
        if ($status !== 'OPEN') {
            throw new CashMovementRejected('La sesión de caja no existe o está cerrada.');
        }

        $categoryId = $this->connection->fetchOne('SELECT id FROM cash_movement_category WHERE code = :code AND active = 1', [
            'code' => $entry->categoryCode,
        ]);
        if ($categoryId === false) {
            throw new CashMovementRejected(sprintf(
                'La categoría de caja «%s» no existe o está inactiva.',
                $entry->categoryCode,
            ));
        }

        $now = new DateTimeImmutable();
        try {
            $this->connection->insert('cash_movements', [
                'cash_session_id' => $entry->sessionId,
                'movement_type' => $entry->isInflow ? 'CASH_IN' : 'CASH_OUT',
                'category_id' => (int) $categoryId,
                'payment_method_id' => $entry->paymentMethodId,
                'amount' => Money::toString($amount),
                'description' => mb_substr($entry->description, 0, 255),
                'occurred_at' => ($entry->occurredAt ?? $now)->format('Y-m-d H:i:s'),
                'created_by' => $entry->userId,
                'idempotency_key' => $entry->idempotencyKey,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent writer appended the same key between the lookup and the insert.
            $winner = $this->find($entry->idempotencyKey);
            if ($winner !== null) {
                return new CashMovementRef($winner->id, $winner->isInflow, $winner->amount, true);
            }
            throw new CashMovementRejected('No se pudo registrar el movimiento de caja.');
        }

        return new CashMovementRef((int) $this->connection->lastInsertId(), $entry->isInflow, Money::toString($amount));
    }

    #[Override]
    public function find(string $idempotencyKey): ?CashMovementRef
    {
        $row = $this->connection->fetchAssociative('SELECT id, movement_type, amount FROM cash_movements WHERE idempotency_key = :key', [
            'key' => $idempotencyKey,
        ]);

        return $row === false
            ? null
            : new CashMovementRef(
                (int) $row['id'],
                (string) $row['movement_type'] === 'CASH_IN',
                Money::toString(Money::money((string) $row['amount'])),
            );
    }
}
