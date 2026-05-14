<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move tenant-scoped application settings from the legacy `config` table to
 * `tenant_settings` while preserving existing values.
 */
final class Version20260504000000_create_tenant_settings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_settings and backfill it from legacy config rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tenant_settings (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                value VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL,
                UNIQUE INDEX UNIQ_TENANT_SETTINGS_NAME (name),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        if ($this->columnExists('tenant_settings', 'name')) {
            $this->addSql(<<<'SQL'
                INSERT INTO tenant_settings (name, value, type)
                SELECT c.name, c.value, c.type
                FROM config c
                WHERE NOT EXISTS (
                    SELECT 1 FROM tenant_settings ts WHERE ts.name = c.name
                )
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO config (name, value, type)
            SELECT ts.name, ts.value, ts.type
            FROM tenant_settings ts
            WHERE NOT EXISTS (
                SELECT 1 FROM config c WHERE c.name = ts.name
            )
        SQL);

        $this->addSql('DROP TABLE IF EXISTS tenant_settings');
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = :table
                AND COLUMN_NAME  = :column',
            ['table' => $table, 'column' => $column]
        );

        return (int) $result->fetchOne() > 0;
    }
}
