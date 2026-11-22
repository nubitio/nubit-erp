<?php

declare(strict_types=1);

namespace App\Identity\Offline;

use Closure;

/**
 * The person who made an offline sale, proven by the signature on it. Whoever's session
 * delivers the sale only carries it; what it may do is decided by this person's permissions.
 */
final readonly class OfflineActor
{
    /** @param Closure(string): bool $permissions whether this person holds a permission */
    public function __construct(
        public int $userId,
        private Closure $permissions,
    ) {}

    public function can(string $permission): bool
    {
        return ($this->permissions)($permission);
    }
}
