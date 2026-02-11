<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to tenants table for soft-delete support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD deleted_at DATETIME DEFAULT NULL AFTER updated_at');
        $this->addSql('CREATE INDEX IDX_TENANT_STATUS ON tenants (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_TENANT_STATUS ON tenants');
        $this->addSql('ALTER TABLE tenants DROP deleted_at');
    }
}
