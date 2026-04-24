<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track payment webhook processing lifecycle for retry-safe idempotency';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE payment_events ADD COLUMN IF NOT EXISTS processing_status VARCHAR(20) NOT NULL DEFAULT 'completed' AFTER payload");
        $this->addSql('ALTER TABLE payment_events ADD COLUMN IF NOT EXISTS last_error VARCHAR(1000) DEFAULT NULL AFTER processed_at');
        $this->addSql('ALTER TABLE payment_events ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT NULL AFTER created_at');
        $this->addSql("UPDATE payment_events SET processing_status = CASE WHEN processed_at IS NULL THEN 'received' ELSE 'completed' END, updated_at = created_at WHERE updated_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_events DROP COLUMN IF EXISTS updated_at');
        $this->addSql('ALTER TABLE payment_events DROP COLUMN IF EXISTS last_error');
        $this->addSql('ALTER TABLE payment_events DROP COLUMN IF EXISTS processing_status');
    }
}
