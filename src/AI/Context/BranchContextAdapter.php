<?php

declare(strict_types=1);

namespace App\AI\Context;

use App\Core\Security\Auth\BranchContextService;

/**
 * Adapts the framework BranchContextService to the AI tool BranchContextInterface.
 *
 * Registered as the concrete service for BranchContextInterface in the DI container.
 */
final readonly class BranchContextAdapter implements BranchContextInterface
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {
    }

    public function getCurrentBranchId(): ?string
    {
        return $this->branchContextService->getCurrentBranchId();
    }
}
