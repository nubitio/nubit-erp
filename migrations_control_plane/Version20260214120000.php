<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin_password column to tenant_provisions for paid onboarding webhook provisioning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_provisions ADD admin_password VARCHAR(255) DEFAULT NULL AFTER admin_user');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_provisions DROP COLUMN admin_password');
    }
}

