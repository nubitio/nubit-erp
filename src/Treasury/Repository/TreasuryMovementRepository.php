<?php

declare(strict_types=1);

namespace App\Treasury\Repository;

use App\Efact\Domain\Money;
use App\Treasury\Domain\Leg;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\Party;
use App\Treasury\Domain\TreasuryMovement;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Append-only: there is deliberately no update or delete method (ADR-003). Corrections are new rows.
 */
final readonly class TreasuryMovementRepository
{
    private const string COLUMNS = 'id, account_id, signed_amount, currency, occurred_at, source_type, source_id, leg, party_type, party_id,
        reference, description, transfer_id, corrects_movement_id, origin, created_by';

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return bool true when the row was written, false when the same (source, id, leg) already existed
     */
    public function append(TreasuryMovement $movement): bool
    {
        try {
            $this->connection->insert('treasury_movement', [
                'id' => $movement->id,
                'account_id' => $movement->accountId,
                'signed_amount' => $movement->signedAmount,
                'currency' => $movement->currency,
                'occurred_on' => $movement->occurredAt->format('Y-m-d'),
                'occurred_at' => $movement->occurredAt->format('Y-m-d H:i:s'),
                'source_type' => $movement->key->source->value,
                'source_id' => $movement->key->id,
                'leg' => $movement->key->leg->value,
                'party_type' => $movement->party?->type,
                'party_id' => $movement->party?->id,
                'reference' => $movement->reference,
                'description' => $movement->description,
                'transfer_id' => $movement->transferId,
                'corrects_movement_id' => $movement->correctsMovementId,
                'origin' => $movement->origin,
                'created_by' => $movement->createdBy,
                'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    public function findByKey(MovementKey $key): ?TreasuryMovement
    {
        $row = $this->connection->fetchAssociative('SELECT '
        . self::COLUMNS
        . ' FROM treasury_movement WHERE source_type = :type AND source_id = :id AND leg = :leg', [
            'type' => $key->source->value,
            'id' => $key->id,
            'leg' => $key->leg->value,
        ]);

        return $row === false ? null : self::hydrate($row);
    }

    /** Balance of a ledger-backed account: the sum of its signed amounts, optionally up to a moment. */
    public function balance(string $accountId, ?DateTimeImmutable $asOf = null): string
    {
        $sql = 'SELECT COALESCE(SUM(signed_amount), 0) FROM treasury_movement WHERE account_id = :account';
        $params = ['account' => $accountId];
        if ($asOf !== null) {
            $sql .= ' AND occurred_at <= :asOf';
            $params['asOf'] = $asOf->format('Y-m-d H:i:s');
        }

        return Money::toString(Money::money((string) $this->connection->fetchOne($sql, $params)));
    }

    /** @return list<TreasuryMovement> newest first */
    public function recent(string $accountId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '
                . self::COLUMNS
                . ' FROM treasury_movement WHERE account_id = :account ORDER BY occurred_at DESC, id DESC LIMIT '
                . max(1, $limit)
                . ' OFFSET '
                . max(0, $offset),
            ['account' => $accountId],
        );

        return array_map(self::hydrate(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): TreasuryMovement
    {
        $nullable = static fn(mixed $value): ?string => $value === null ? null : (string) $value;

        return new TreasuryMovement(
            id: (string) $row['id'],
            accountId: (string) $row['account_id'],
            signedAmount: Money::toString(Money::money((string) $row['signed_amount'])),
            currency: (string) $row['currency'],
            occurredAt: new DateTimeImmutable((string) $row['occurred_at']),
            key: new MovementKey(
                MovementSource::from((string) $row['source_type']),
                (string) $row['source_id'],
                Leg::from((string) $row['leg']),
            ),
            party: $row['party_type'] === null || $row['party_id'] === null
                ? null
                : new Party((string) $row['party_type'], (string) $row['party_id']),
            reference: $nullable($row['reference']),
            description: $nullable($row['description']),
            transferId: $nullable($row['transfer_id']),
            correctsMovementId: $nullable($row['corrects_movement_id']),
            origin: (string) $row['origin'],
            createdBy: $row['created_by'] === null ? null : (int) $row['created_by'],
        );
    }
}
