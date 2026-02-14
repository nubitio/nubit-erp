<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link subscriptions to tenant_provisions via provision_id for deterministic paid onboarding provisioning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD provision_id INT UNSIGNED DEFAULT NULL AFTER account_id');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTIONS_PROVISION ON subscriptions (provision_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_SUBSCRIPTIONS_PROVISION ON subscriptions');
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN provision_id');
    }
}

