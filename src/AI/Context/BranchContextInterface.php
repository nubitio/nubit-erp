<?php

declare(strict_types=1);

namespace App\AI\Context;

/**
 * Provides the current branch identifier for multi-tenant scoping within AI tools.
 */
interface BranchContextInterface
{
    /**
     * Returns the current branch ID, or null if no branch is selected.
     */
    public function getCurrentBranchId(): ?string;
}
