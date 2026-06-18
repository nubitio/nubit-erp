<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the vertical (business type) column to tenant_settings.
 *
 * Stores the onboarding-selected vertical preset key (e.g. "retail",
 * "pharmacy") so the runtime config reports the real vertical instead of
 * inferring it from topology. Nullable-safe via NOT NULL DEFAULT 'retail'.
 */
final class Version20260618000000_tenant_settings_vertical extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add vertical VARCHAR(64) NOT NULL DEFAULT 'retail' to tenant_settings";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant_settings ADD COLUMN IF NOT EXISTS vertical VARCHAR(64) NOT NULL DEFAULT 'retail'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings DROP COLUMN vertical');
    }
}
