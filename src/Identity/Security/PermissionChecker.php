<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Entity\User;
use App\Identity\Repository\RolePermissionRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves whether the connected user's role holds a granular permission.
 * ROLE_SUPER_ADMIN holds every permission implicitly. Grants are loaded
 * once per role per request.
 */
final class PermissionChecker implements PermissionCheckerInterface
{
    /** @var array<int, list<string>> */
    private array $grantsByRole = [];

    public function __construct(
        private readonly Security $security,
        private readonly RolePermissionRepository $rolePermissions,
    ) {}

    public function hasPermission(string $permission): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), strict: true)) {
            return true;
        }

        $roleId = $user->getRole()?->getId();

        if (null === $roleId) {
            return false;
        }

        $this->grantsByRole[$roleId] ??= $this->rolePermissions->findPermissionKeysByRoleId($roleId);

        return in_array($permission, $this->grantsByRole[$roleId], strict: true);
    }

    /**
     * All permission keys granted to a user. Super admins get the '*'
     * wildcard, which the frontend treats as "everything".
     *
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), strict: true)) {
            return ['*'];
        }

        $roleId = $user->getRole()?->getId();

        if (null === $roleId) {
            return [];
        }

        $this->grantsByRole[$roleId] ??= $this->rolePermissions->findPermissionKeysByRoleId($roleId);

        return $this->grantsByRole[$roleId];
    }
}
