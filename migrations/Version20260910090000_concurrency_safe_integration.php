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
        $this->addSql('ALTER TABLE integration_event ADD claim_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD claim_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE integration_webhook_delivery ADD claim_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD claim_token VARCHAR(64) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DELETE d1 FROM integration_webhook_delivery d1
            INNER JOIN integration_webhook_delivery d2
                ON d1.event_id = d2.event_id
               AND d1.endpoint_id = d2.endpoint_id
               AND (d1.created_at > d2.created_at OR (d1.created_at = d2.created_at AND d1.id > d2.id))
        SQL);
        $this->addSql('ALTER TABLE integration_webhook_delivery ADD UNIQUE INDEX uq_webhook_delivery_event_endpoint (event_id, endpoint_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_webhook_delivery DROP INDEX uq_webhook_delivery_event_endpoint, DROP claim_until, DROP claim_token');
        $this->addSql('ALTER TABLE integration_event DROP claim_until, DROP claim_token');
    }
}
