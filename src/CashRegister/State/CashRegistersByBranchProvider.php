<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Repository\CashRegisterRepository;
use App\Identity\Auth\BranchContextServiceInterface;

/**
 * Provides the list of cash registers for the branch extracted from the JWT token.
 * No query parameter needed — branch isolation is enforced server-side.
 *
 * @implements ProviderInterface<CashRegister>
 */
final readonly class CashRegistersByBranchProvider implements ProviderInterface
{
    public function __construct(
        private CashRegisterRepository $repository,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @return list<CashRegister>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $branchId = $this->branchContext->getCurrentBranchId();

        if ($branchId === null) {
            return [];
        }

        return $this->repository->findBy(['branch' => $branchId]);
    }
}
