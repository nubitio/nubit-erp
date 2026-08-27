<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track onboarding reminders and abandoned-tenant lifecycle notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE accounts ADD onboarding_reminder_stage SMALLINT UNSIGNED NOT NULL DEFAULT 0, ADD onboarding_reminded_at DATETIME DEFAULT NULL',
        );
        $this->addSql(
            'ALTER TABLE tenants ADD onboarding_reminder_stage SMALLINT UNSIGNED NOT NULL DEFAULT 0, ADD onboarding_reminded_at DATETIME DEFAULT NULL, ADD abandonment_warned_at DATETIME DEFAULT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounts DROP onboarding_reminder_stage, DROP onboarding_reminded_at');
        $this->addSql(
            'ALTER TABLE tenants DROP onboarding_reminder_stage, DROP onboarding_reminded_at, DROP abandonment_warned_at',
        );
    }
}
