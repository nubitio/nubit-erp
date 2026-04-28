<?php

declare(strict_types=1);

namespace App\Identity\Notification;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Writes in-app notifications to the tenant DB.
 *
 * Uses raw DBAL (not ORM) so it can be called safely from Doctrine lifecycle
 * listeners without risking flush-inside-flush issues.
 *
 * Role → rol_id mapping mirrors User::getRoles():
 *   1 = ROLE_SUPER_ADMIN
 *   2 = ROLE_ADMIN
 *   3 = ROLE_CASHIER
 */
final readonly class UserNotificationService implements UserNotificationServiceInterface
{
    /** @var array<string, int> */
    private const array ROLE_INT_MAP = [
        'ROLE_SUPER_ADMIN' => 1,
        'ROLE_ADMIN'       => 2,
        'ROLE_CASHIER'     => 3,
    ];

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Notify a single user by their ID.
     *
     * @throws Exception
     */
    public function notifyUser(
        int $userId,
        string $type,
        string $severity,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void {
        $this->connection->insert('user_notification', [
            'user_id'    => $userId,
            'type'       => $type,
            'severity'   => $severity,
            'title'      => $title,
            'body'       => $body,
            'action_url' => $actionUrl,
            'read_at'    => null,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notify all users whose rol_id matches any of the given symbolic role strings.
     *
     * Roles recognised: ROLE_SUPER_ADMIN (1), ROLE_ADMIN (2), ROLE_CASHIER (3).
     * Unknown role strings are silently ignored.
     *
     * @param list<string> $roles  e.g. ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']
     * @throws Exception
     */
    public function notifyByRoles(
        array $roles,
        string $type,
        string $severity,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void {
        $rolIds = $this->rolesToInts($roles);

        if ($rolIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($rolIds), '?'));

        /** @var list<array{usuario_id: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT usuario_id FROM usuario WHERE rol_id IN ({$placeholders})",
            $rolIds,
        );

        if ($rows === []) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $this->connection->insert('user_notification', [
                'user_id'    => (int) $row['usuario_id'],
                'type'       => $type,
                'severity'   => $severity,
                'title'      => $title,
                'body'       => $body,
                'action_url' => $actionUrl,
                'read_at'    => null,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Deduplication guard: returns true if a notification of the given type
     * was already created for this user within the last $withinSeconds seconds.
     *
     * @throws Exception
     */
    public function hasDuplicateRecent(int $userId, string $type, int $withinSeconds = 86400): bool
    {
        $since = (new DateTimeImmutable())->modify("-{$withinSeconds} seconds")->format('Y-m-d H:i:s');

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_notification WHERE user_id = ? AND type = ? AND created_at >= ?',
            [$userId, $type, $since],
        );

        return $count > 0;
    }

    /**
     * Deduplicated version of notifyByRoles — skips users that already have a
     * recent notification of this type within the given window.
     *
     * @param list<string> $roles
     * @throws Exception
     */
    public function notifyByRolesDeduped(
        array $roles,
        string $type,
        string $severity,
        string $title,
        string $body,
        ?string $actionUrl = null,
        int $withinSeconds = 86400,
    ): void {
        $rolIds = $this->rolesToInts($roles);

        if ($rolIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($rolIds), '?'));

        /** @var list<array{usuario_id: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT usuario_id FROM usuario WHERE rol_id IN ({$placeholders})",
            $rolIds,
        );

        if ($rows === []) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $userId = (int) $row['usuario_id'];

            if ($this->hasDuplicateRecent($userId, $type, $withinSeconds)) {
                continue;
            }

            $this->connection->insert('user_notification', [
                'user_id'    => $userId,
                'type'       => $type,
                'severity'   => $severity,
                'title'      => $title,
                'body'       => $body,
                'action_url' => $actionUrl,
                'read_at'    => null,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @param list<string> $roles
     * @return list<int>
     */
    private function rolesToInts(array $roles): array
    {
        $result = [];
        foreach ($roles as $role) {
            if (isset(self::ROLE_INT_MAP[$role])) {
                $result[] = self::ROLE_INT_MAP[$role];
            }
        }

        return array_values(array_unique($result));
    }
}
