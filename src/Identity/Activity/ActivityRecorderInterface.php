<?php

declare(strict_types=1);

namespace App\Identity\Activity;

use App\Identity\Entity\User;

/**
 * Appends an entry to the tenant's activity record. Implementations must never throw: the record
 * is an observer of business operations and must not be able to break them.
 */
interface ActivityRecorderInterface
{
    /**
     * @param User|string|null $actor the acting user; a string is a username (e.g. a failed sign-in
     *                                for an account that may not exist); null means "the signed-in user, if any"
     */
    public function record(
        string $type,
        string $summary,
        ?string $detail = null,
        ?string $subject = null,
        User|string|null $actor = null,
    ): void;
}
