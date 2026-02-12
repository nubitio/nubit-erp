<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel column to audit_logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_logs ADD COLUMN channel VARCHAR(32) DEFAULT NULL AFTER request_id');
        $this->addSql('CREATE INDEX IDX_AUDIT_CHANNEL ON audit_logs (channel)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_AUDIT_CHANNEL ON audit_logs');
        $this->addSql('ALTER TABLE audit_logs DROP COLUMN channel');
    }
}
