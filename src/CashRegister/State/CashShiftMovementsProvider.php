<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Enum\CashMovementType;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<CashMovement>
 */
readonly class CashShiftMovementsProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     * @return CashMovement[]
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $cashShiftId = $uriVariables['id'];

        // Step 1: try direct v2 session ID
        $session = $this->connection->fetchAssociative(
            'SELECT id FROM cash_sessions WHERE id = ?',
            [$cashShiftId]
        );

        // Step 2: fallback — treat URI id as a legacy_cash_shift_id
        if ($session === false) {
            $session = $this->connection->fetchAssociative(
                'SELECT id FROM cash_sessions WHERE legacy_cash_shift_id = ?',
                [$cashShiftId]
            );
        }

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashShiftId));
        }

        $sessionId = (int) $session['id'];

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, cash_session_id, category_id, amount, description, created_at, movement_type
             FROM cash_movements
             WHERE cash_session_id = ?
             ORDER BY created_at ASC',
            [$sessionId]
        );

        $idProp = new \ReflectionProperty(CashMovement::class, 'id');

        // Pre-load the CashSession entity once for all rows
        $cashSessionEntity = $this->entityManager->find(CashSession::class, $sessionId);

        /** @var CashMovement[] $movements */
        $movements = [];
        foreach ($rows as $row) {
            $movement = new CashMovement();
            $type     = match ($row['movement_type']) {
                'CASH_OUT' => CashMovementType::EXPENSE,
                default    => CashMovementType::INCOME,
            };
            $movement->setType($type);
            $movement->setAmount((string) $row['amount']);
            $movement->setDescription((string) ($row['description'] ?? 'Movimiento de caja'));
            $movement->setCreatedAt(new DateTimeImmutable((string) $row['created_at']));
            $idProp->setValue($movement, (int) $row['id']);

            if ($cashSessionEntity instanceof CashSession) {
                $movement->setCashSession($cashSessionEntity);
            }

            if ($row['category_id'] !== null) {
                $category = $this->entityManager->find(CashMovementCategory::class, (int) $row['category_id']);
                if ($category instanceof CashMovementCategory) {
                    $movement->setCategory($category);
                }
            }

            $movements[] = $movement;
        }

        return $movements;
    }
}
