<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Organization\Entity\Branch;

/**
 * Request-scoped holder for the branch a token pair is being issued for.
 * AuthSelectBranchController sets it right before calling the bundle's
 * TokenGenerator; EfactTokenClaimsProvider reads it when building claims.
 */
final class SelectedBranchContext
{
    private ?Branch $branch = null;

    public function set(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function get(): ?Branch
    {
        return $this->branch;
    }
}
