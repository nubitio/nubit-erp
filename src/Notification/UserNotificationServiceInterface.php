<?php

declare(strict_types=1);

namespace App\Notification;

interface UserNotificationServiceInterface
{
    /**
     * Notify a single user by their ID.
     */
    public function notifyUser(
        int $userId,
        string $type,
        string $severity,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void;

    /**
     * Notify all users whose rol_id matches any of the given symbolic role strings.
     *
     * @param list<string> $roles  e.g. ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']
     */
    public function notifyByRoles(
        array $roles,
        string $type,
        string $severity,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void;

    /**
     * Returns true if a notification of the given type was already created
     * for this user within the last $withinSeconds seconds.
     */
    public function hasDuplicateRecent(int $userId, string $type, int $withinSeconds = 86400): bool;

    /**
     * Deduplicated version of notifyByRoles — skips users that already have a
     * recent notification of this type within the given window.
     *
     * @param list<string> $roles
     */
    public function notifyByRolesDeduped(
        array $roles,
        string $type,
        string $severity,
        string $title,
        string $body,
        ?string $actionUrl = null,
        int $withinSeconds = 86400,
    ): void;
}
