<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Identity\Entity\User;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
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
        private OperatingBranchResolver $branches,
    ) {}

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

        $user = $token->getUser();

        return $user instanceof User
            ? $this->branches->resolve($user, $payload['branchId'] ?? null)?->getId()
            : null;
    }

    /**
     * Check if a branch is selected in the current request
     */
    public function hasBranch(): bool
    {
        return $this->getCurrentBranchId() !== null;
    }
}
