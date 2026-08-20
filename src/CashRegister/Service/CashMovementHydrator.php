<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Enum\CashMovementType;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;

/**
 * Builds a `CashMovement` (the "mixed legacy DTO" — see its own docblock)
 * from a raw `cash_movements` (v2, plural) row. `CashMovement` itself is
 * still an ORM entity mapped to the legacy singular `cash_movement` table
 * for backward compatibility with any pre-cutover consumer that still
 * expects `EntityManager::find(CashMovement::class, $id)` to resolve a
 * historical row — this hydrator is how every v2-aware read path (item,
 * collection, session-scoped, and BankReconciliation) builds the same
 * shape from the table application code actually writes to, without
 * duplicating this mapping in four places. See ADR-004's amendment
 * (docs/adr/ADR-004-cashregister-strangler-fig-decisions.md) for why both
 * tables still exist.
 */
final readonly class CashMovementHydrator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {}

    /**
     * Looks up a single v2 cash movement by id — the v2-aware equivalent of
     * `EntityManager::find(CashMovement::class, $id)`, which would incorrectly
     * resolve against the frozen legacy `cash_movement` table instead.
     *
     * @throws Exception
     */
    public function findById(int $id): ?CashMovement
    {
        $row = $this->connection->fetchAssociative('SELECT id, cash_session_id, movement_type, category_id, amount, description, created_at
             FROM cash_movements
             WHERE id = ?', [$id]);

        return $row === false ? null : $this->fromRow($row);
    }

    /**
     * @param array{id: int|string, cash_session_id: int|string|null, category_id: int|string|null, amount: string, description: string|null, created_at: string, movement_type: string} $row
     */
    public function fromRow(array $row): CashMovement
    {
        $movement = new CashMovement();

        $type = match ($row['movement_type']) {
            'CASH_OUT' => CashMovementType::EXPENSE,
            default => CashMovementType::INCOME,
        };
        $movement->setType($type);
        $movement->setAmount((string) $row['amount']);
        $movement->setDescription((string) ($row['description'] ?? 'Movimiento de caja'));
        $movement->setCreatedAt(new DateTimeImmutable((string) $row['created_at']));

        new ReflectionProperty(CashMovement::class, 'id')->setValue($movement, (int) $row['id']);

        if ($row['cash_session_id'] !== null) {
            $session = $this->entityManager->find(CashSession::class, (int) $row['cash_session_id']);
            if ($session instanceof CashSession) {
                $movement->setCashSession($session);
            }
        }

        if ($row['category_id'] !== null) {
            $category = $this->entityManager->find(CashMovementCategory::class, (int) $row['category_id']);
            if ($category instanceof CashMovementCategory) {
                $movement->setCategory($category);
            }
        }

        return $movement;
    }
}
