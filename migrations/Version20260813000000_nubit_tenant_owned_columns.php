<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813000000_nubit_tenant_owned_columns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'nubitio/admin-bundle 0.12 makes AuditLog and Media implement TenantOwnedInterface, '
            . 'adding a nullable tenant_id column to nubit_audit_log and nubit_media';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_audit_log ADD tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nubit_media ADD tenant_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_audit_log DROP COLUMN tenant_id');
        $this->addSql('ALTER TABLE nubit_media DROP COLUMN tenant_id');
    }
}
