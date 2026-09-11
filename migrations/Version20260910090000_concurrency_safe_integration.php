<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910090000_concurrency_safe_integration extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add integration leases and enforce one webhook delivery per event and endpoint';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne(<<<'SQL'
                SELECT COUNT(*) FROM (
                    SELECT event_id, endpoint_id
                    FROM integration_webhook_delivery
                    GROUP BY event_id, endpoint_id
                    HAVING COUNT(*) > 1
                ) duplicates
            SQL) > 0,
            'Cannot enforce webhook delivery uniqueness: duplicate rows exist. Resolve them manually; this migration never deletes delivery history.',
        );
        $this->addSql('ALTER TABLE integration_event ADD claim_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD claim_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE integration_webhook_delivery ADD claim_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD claim_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE integration_webhook_delivery ADD UNIQUE INDEX uq_webhook_delivery_event_endpoint (event_id, endpoint_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_webhook_delivery DROP INDEX uq_webhook_delivery_event_endpoint, DROP claim_until, DROP claim_token');
        $this->addSql('ALTER TABLE integration_event DROP claim_until, DROP claim_token');
    }
}
