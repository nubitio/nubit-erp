<?php

declare(strict_types=1);

namespace App\Identity\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Security Voter that handles IS_GRANTED('perm:<module.action>') expressions.
 *
 * Attribute format:  'perm:<permission key from PermissionCatalog>'
 * Example:           is_granted('perm:sales.void')
 *                    → checks the connected user's role grants via PermissionChecker
 *
 * ROLE_SUPER_ADMIN is always granted. The voter abstains for attributes
 * without the 'perm:' prefix.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    private const string PREFIX = 'perm:';

    public function __construct(
        private readonly PermissionCheckerInterface $permissionChecker,
    ) {}

    /**
     * The $subject is intentionally ignored — permissions are role-scoped,
     * not object-scoped.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::PREFIX);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        #[\SensitiveParameter]
        TokenInterface $token,
        ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null,
    ): bool {
        $permission = substr($attribute, \strlen(self::PREFIX));

        return $this->permissionChecker->hasPermission($permission);
    }
}
