<?php

declare(strict_types=1);

namespace App\Tests\Identity\Activity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/** In-memory identity_activity_event table shaped like the migration's, for the recorder and query tests. */
final class ActivityTestDatabase
{
    public static function create(): Connection
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE identity_activity_event (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                occurred_at TEXT NOT NULL,
                actor_user_id INTEGER NULL,
                actor_username TEXT NULL,
                event_type TEXT NOT NULL,
                is_sensitive INTEGER NOT NULL DEFAULT 0,
                summary TEXT NOT NULL,
                detail TEXT NULL,
                subject TEXT NULL,
                ip TEXT NULL,
                user_agent TEXT NULL
            )');

        return $db;
    }

    /** @param array<string, mixed> $row */
    public static function insert(Connection $db, array $row): void
    {
        $db->insert(
            'identity_activity_event',
            $row
            + [
                'occurred_at' => '2026-11-07 15:00:00',
                'actor_username' => null,
                'event_type' => 'auth.login',
                'is_sensitive' => 0,
                'summary' => 'x',
            ],
        );
    }
}
