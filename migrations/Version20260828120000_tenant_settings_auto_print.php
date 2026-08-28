<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the `auto_print` tenant setting: whether the POS sends the sale ticket to
 * the printer automatically after checkout. Defaults to 1 to preserve the
 * current behaviour for existing tenants.
 */
final class Version20260828120000_tenant_settings_auto_print extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto_print flag to tenant_settings (default on).';
    }

    public function up(Schema $schema): void
    {
        if ($this->columnExists('tenant_settings', 'auto_print')) {
            return;
        }

        $this->addSql('ALTER TABLE tenant_settings ADD auto_print TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        if (!$this->columnExists('tenant_settings', 'auto_print')) {
            return;
        }

        $this->addSql('ALTER TABLE tenant_settings DROP auto_print');
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
