<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use Nubit\Platform\Exception\ValidationException;

final readonly class OperationalBranchGuard
{
    public function __construct(
        private BranchContextServiceInterface $context,
        private BranchRepository $branches,
    ) {}

    /** @param array<string, Branch|null> $references Supplied references whose branch is required. */
    public function requireBranch(array $references = [], ?string $authorizedBranchId = null): Branch
    {
        $id = $authorizedBranchId ?? $this->context->getCurrentBranchId();
        $branch = $id === null ? null : $this->branches->find($id);
        if ($branch === null || !$branch->getIsActive()) {
            throw ValidationException::withErrors([
                'branch' => ['Selecciona una sucursal activa para continuar.'],
            ]);
        }

        foreach ($references as $field => $reference) {
            if ($reference?->getId() !== $id) {
                throw ValidationException::withErrors([
                    $field => ['Selecciona un registro de la sucursal activa.'],
                ]);
            }
        }

        return $branch;
    }
}
