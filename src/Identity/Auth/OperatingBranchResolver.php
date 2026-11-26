<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;

final readonly class OperatingBranchResolver
{
    public function __construct(private BranchRepository $branches) {}

    public function resolve(User $user, mixed $branchId): ?Branch
    {
        if (!is_string($branchId) || $branchId === '' || !$user->canOperateInBranch($branchId)) {
            return null;
        }

        $branch = $this->branches->find($branchId);

        return $branch?->getIsActive() ? $branch : null;
    }
}
