<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Entity\User;

interface PermissionCheckerInterface
{
    /**
     * Whether the connected user's role holds the given permission key.
     */
    public function hasPermission(string $permission): bool;

    /**
     * All permission keys granted to a user. Super admins get the '*'
     * wildcard, which the frontend treats as "everything".
     *
     * @return list<string>
     */
    public function permissionsFor(User $user): array;
}
