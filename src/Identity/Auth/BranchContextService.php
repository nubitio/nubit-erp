<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Throwable;

/**
 * Service to get the current branch ID from the authenticated JWT token
 */
final readonly class BranchContextService implements BranchContextServiceInterface
{
    public function __construct(
        private SecurityComponent $security,
        private JWTManagerInterface $jwtManager,
    ) {
    }

    /**
     * Get the branch ID from the current JWT token
     */
    public function getCurrentBranchId(): ?string
    {
        $token = $this->security->getToken();
        if (!$token instanceof JWTAuthenticationToken) {
            return null;
        }

        try {
            $payload = $this->jwtManager->decode($token->getCredentials());
        } catch (Throwable) {
            return null;
        }

        $branchId = $payload['branchId'] ?? null;

        return $branchId !== null ? (string)$branchId : null;
    }

    /**
     * Check if a branch is selected in the current request
     */
    public function hasBranch(): bool
    {
        return $this->getCurrentBranchId() !== null;
    }
}
