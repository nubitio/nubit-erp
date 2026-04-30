<?php

declare(strict_types=1);

namespace App\Identity\Auth;

interface BranchContextServiceInterface
{
    public function getCurrentBranchId(): ?string;

    public function hasBranch(): bool;
}
