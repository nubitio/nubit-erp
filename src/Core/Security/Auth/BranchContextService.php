<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service to get the current branch ID from the request header
 */
final readonly class BranchContextService
{
    public const string BRANCH_HEADER = 'X-Branch-Id';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Get the branch ID from the current request header
     */
    public function getCurrentBranchId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $branchId = $request->headers->get(self::BRANCH_HEADER);

        return $branchId ? (int)$branchId : null;
    }

    /**
     * Check if a branch is selected in the current request
     */
    public function hasBranch(): bool
    {
        return $this->getCurrentBranchId() !== null;
    }
}
