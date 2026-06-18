<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the tenant onboarding completion flag.
 */
final class Version20260618000001_tenant_settings_onboarding_completed extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add onboarding_completed BOOLEAN NOT NULL DEFAULT FALSE to tenant_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings ADD COLUMN IF NOT EXISTS onboarding_completed BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings DROP COLUMN onboarding_completed');
    }
}
