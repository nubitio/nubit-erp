<?php

declare(strict_types=1);

namespace App\CashRegister\State;

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
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Custom item provider that reads a single CashMovement from the v2 cash_movements table.
 * Used for GET /api/cash-movements/{id} and as the provider for PATCH/DELETE operations.
 *
 * @implements ProviderInterface<CashMovement>
 */
readonly class CashMovementItemProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CashMovement
    {
        $id = (int) ($uriVariables['id'] ?? 0);

        $row = $this->connection->fetchAssociative('SELECT id, cash_session_id, movement_type, category_id, amount, description, created_at
             FROM cash_movements
             WHERE id = ?', [$id]);

        if ($row === false) {
            throw new NotFoundHttpException(sprintf('CashMovement not found: %d', $id));
        }

        $movement = new CashMovement();

        // Set the ID via reflection, matching the collection provider pattern.
        $idProp = new \ReflectionProperty(CashMovement::class, 'id');
        $idProp->setValue($movement, (int) $row['id']);

        $type = match ($row['movement_type']) {
            'CASH_OUT' => CashMovementType::EXPENSE,
            default => CashMovementType::INCOME,
        };
        $movement->setType($type);
        $movement->setAmount((string) $row['amount']);
        $movement->setDescription((string) ($row['description'] ?? ''));
        $movement->setCreatedAt(new DateTimeImmutable((string) $row['created_at']));

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
