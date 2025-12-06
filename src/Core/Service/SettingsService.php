<?php

namespace App\Core\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class SettingsService
{
    public function __construct(
        private Connection $db,
    ) {
    }

    /**
     * @throws Exception
     */
    public function get(string $key, ?string $default = null): bool | float | int | string | null
    {
        /** @var bool|float|int|string|null $value */
        $value = $this->db->fetchOne('SELECT value FROM config WHERE name = ?', [$key]);

        if (false === $value) {
            return $default;
        }

        return $value;
    }

    /**
     * @throws Exception
     */
    public function set(string $key, string $type, mixed $value): void
    {
        $this->db->executeStatement(
            'INSERT INTO config (name, type, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?',
            [$key, $type, $value, $value]
        );
    }
}
