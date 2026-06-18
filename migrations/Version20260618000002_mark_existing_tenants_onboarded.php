<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Existing tenants predate the React onboarding wizard and must not be gated.
 *
 * New tenants still start with TenantSettings::$onboardingCompleted = false
 * because the entity value is written during initial settings creation.
 */
final class Version20260618000002_mark_existing_tenants_onboarded extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark existing tenant_settings rows as onboarding_completed = TRUE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE tenant_settings SET onboarding_completed = TRUE WHERE onboarding_completed = FALSE');
    }

    public function down(Schema $schema): void
    {
        // Irreversible safely: this migration protects existing tenants from
        // being gated by a wizard introduced after they were provisioned.
    }
}
