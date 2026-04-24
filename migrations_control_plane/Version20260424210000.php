<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track recoverable Lycet certificate synchronization state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant_certificates ADD lycet_sync_status VARCHAR(32) NOT NULL DEFAULT 'synced' AFTER last_alerted_at");
        $this->addSql('ALTER TABLE tenant_certificates ADD lycet_sync_error VARCHAR(1000) DEFAULT NULL AFTER lycet_sync_status');
        $this->addSql('ALTER TABLE tenant_certificates ADD lycet_sync_updated_at DATETIME DEFAULT NULL AFTER lycet_sync_error');
        $this->addSql('CREATE INDEX IDX_TENANT_CERT_LYCET_SYNC ON tenant_certificates (lycet_sync_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_TENANT_CERT_LYCET_SYNC ON tenant_certificates');
        $this->addSql('ALTER TABLE tenant_certificates DROP COLUMN lycet_sync_updated_at');
        $this->addSql('ALTER TABLE tenant_certificates DROP COLUMN lycet_sync_error');
        $this->addSql('ALTER TABLE tenant_certificates DROP COLUMN lycet_sync_status');
    }
}
