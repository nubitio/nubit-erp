<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add billing_cycle column to subscriptions for annual plan support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE subscriptions
            ADD COLUMN IF NOT EXISTS billing_cycle VARCHAR(10) NOT NULL DEFAULT 'monthly'
                COMMENT 'monthly or annual'
            AFTER plan
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN IF EXISTS billing_cycle');
    }
}
