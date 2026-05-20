<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520000000_tenant_settings_singleton extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce tenant_settings as a singleton table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE ts
            FROM tenant_settings ts
            JOIN (SELECT MAX(id) AS keep_id FROM tenant_settings) keep_row
              ON ts.id <> keep_row.keep_id
        SQL);

        $this->addSql('ALTER TABLE tenant_settings ADD singleton_key TINYINT NOT NULL DEFAULT 1');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TENANT_SETTINGS_SINGLETON ON tenant_settings (singleton_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_TENANT_SETTINGS_SINGLETON ON tenant_settings');
        $this->addSql('ALTER TABLE tenant_settings DROP singleton_key');
    }
}
