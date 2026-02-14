<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dismissed_at column to tenant_provisions for hidden failed onboarding alerts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_provisions ADD dismissed_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_TENANT_PROVISIONS_DISMISSED_AT ON tenant_provisions (dismissed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_TENANT_PROVISIONS_DISMISSED_AT ON tenant_provisions');
        $this->addSql('ALTER TABLE tenant_provisions DROP dismissed_at');
    }
}

