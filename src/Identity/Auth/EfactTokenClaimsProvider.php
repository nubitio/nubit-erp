<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Identity\Entity\User;
use App\Identity\Security\PermissionCheckerInterface;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use Nubit\AdminBundle\Auth\TokenClaimsProviderInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * efact token claims: user id + role, branch (from the select-branch flow or
 * carried over from the rotated token on refresh), and the active tenant.
 * Replaces the payload that Identity's TokenGeneratorService used to build.
 */
final readonly class EfactTokenClaimsProvider implements TokenClaimsProviderInterface
{
    public function __construct(
        private SelectedBranchContext $selectedBranch,
        private TenantContext $tenantContext,
        private PermissionCheckerInterface $permissionChecker,
        private BranchRepository $branchRepository,
        private CompanyRepository $companyRepository,
    ) {}

    public function claims(UserInterface $user, array $previousClaims = []): array
    {
        $this->assertPreviousBranchStillAuthorized($user, $previousClaims);
        $branch = $this->selectedBranch->get();

        // On refresh, carry the branch claims of the rotated token forward —
        // the signed JWT is the source of truth for the active branch.
        $branchId = $branch?->getId() ?? $previousClaims['branchId'] ?? null;
        $branchName = $branch?->getName() ?? $previousClaims['branchName'] ?? null;

        // Support impersonation (see ImpersonationApiController) stamps the
        // acting control-plane admin here at mint time; carried forward on
        // refresh the same way branch claims are, since the signed JWT is
        // the source of truth.
        $impersonatedBy = $previousClaims['impersonatedBy'] ?? null;

        return [
            'userId' => $user instanceof User ? $user->getId() : null,
            'role' => $user instanceof User ? $user->getRole()?->getId() : null,
            'roles' => $user->getRoles(),
            'branchId' => $branchId,
            'branchName' => $branchName,
            'tenantId' => $this->tenantContext->getTenantId(),
            'tenantName' => $this->tenantContext->getTenantName(),
            'impersonatedBy' => $impersonatedBy,
        ];
    }

    public function userData(UserInterface $user): array
    {
        $userId = null;
        $role = null;
        $permissions = [];
        if ($user instanceof User) {
            $userId = $user->getId();
            $role = $user->getRole()?->getId();
            $permissions = $this->permissionChecker->permissionsFor($user);
        }

        return [
            'username' => $user->getUserIdentifier(),
            'userId' => $userId,
            'role' => $role,
            'roles' => $user->getRoles(),
            'permissions' => $permissions,
        ];
    }

    /** @param array<string, mixed> $previousClaims */
    private function assertPreviousBranchStillAuthorized(UserInterface $user, array $previousClaims): void
    {
        $branchId = is_string($previousClaims['branchId'] ?? null) ? $previousClaims['branchId'] : null;
        if (null === $branchId || '' === $branchId) {
            return;
        }
        $branch = $this->branchRepository->find($branchId);
        if (
            !$user instanceof User
            || null === $this->tenantContext->getTenantId()
            || null === $branch
            || !$user->hasBranchAccess($branch)
        ) {
            throw new \RuntimeException('The refresh token branch is no longer authorized.');
        }

        $company = $this->companyRepository->findOneBy([]);
        $branchCompany = $branch->getCompany();
        if (null === $company || null === $branchCompany || $company->getId() !== $branchCompany->getId()) {
            throw new \RuntimeException('The refresh token tenant is no longer authorized.');
        }
    }
}
