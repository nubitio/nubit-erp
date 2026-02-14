<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add backup_type to tenant_backups and backup_retention_days to tenants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant_backups ADD backup_type VARCHAR(16) NOT NULL DEFAULT 'full' AFTER status");
        $this->addSql('CREATE INDEX IDX_TENANT_BACKUPS_TYPE ON tenant_backups (backup_type)');
        $this->addSql('CREATE INDEX IDX_TENANT_BACKUPS_PRUNE ON tenant_backups (tenant_id, backup_type, status, completed_at)');
        $this->addSql('ALTER TABLE tenants ADD backup_retention_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER backup_frequency');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_backups DROP INDEX IDX_TENANT_BACKUPS_PRUNE');
        $this->addSql('ALTER TABLE tenant_backups DROP INDEX IDX_TENANT_BACKUPS_TYPE');
        $this->addSql('ALTER TABLE tenant_backups DROP COLUMN backup_type');
        $this->addSql('ALTER TABLE tenants DROP COLUMN backup_retention_days');
    }
}
