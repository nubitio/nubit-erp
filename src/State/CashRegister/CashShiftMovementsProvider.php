<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CashMovement;
use App\Enum\CashMovementType;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

readonly class CashShiftMovementsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $cashShiftId = $uriVariables['id'];

        $movements = $this->entityManager->getRepository(CashMovement::class)->findBy(
            ['cashShift' => $cashShiftId],
            ['createdAt' => 'ASC', 'id' => 'ASC']
        );

        $v2Rows = $this->connection->fetchAllAssociative(
            "SELECT cm2.id, cm2.amount, cm2.description, cm2.created_at, cm2.movement_type
             FROM cash_movements cm2
             JOIN cash_sessions cs ON cs.id = cm2.cash_session_id
             WHERE cs.legacy_cash_shift_id = ?
             ORDER BY cm2.created_at ASC",
            [$cashShiftId]
        );

        $idProp = new \ReflectionProperty(CashMovement::class, 'id');
        foreach ($v2Rows as $row) {
            $movement = new CashMovement();
            $type = $row['movement_type'] === 'SALE_IN' ? CashMovementType::INCOME : CashMovementType::EXPENSE;
            $movement->setType($type);
            $movement->setAmount((string) $row['amount']);
            $movement->setDescription($row['description'] ?? 'Venta en efectivo');
            $movement->setCreatedAt(new DateTimeImmutable($row['created_at']));
            $idProp->setValue($movement, -(int) $row['id']); // negative to avoid collision with legacy IDs
            $movements[] = $movement;
        }

        usort($movements, static fn (CashMovement $a, CashMovement $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $movements;
    }
}
