<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Service\CashMovementHydrator;
use Doctrine\DBAL\Exception;
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
        private CashMovementHydrator $hydrator,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CashMovement
    {
        $id = (int) ($uriVariables['id'] ?? 0);

        $movement = $this->hydrator->findById($id);
        if ($movement === null) {
            throw new NotFoundHttpException(sprintf('CashMovement not found: %d', $id));
        }

        return $movement;
    }
}
