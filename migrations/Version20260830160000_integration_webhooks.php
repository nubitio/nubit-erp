<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Webhook endpoints + delivery attempts for the integration event bus.
 */
final class Version20260830160000_integration_webhooks extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create integration_webhook_endpoint and integration_webhook_delivery tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_webhook_endpoint (
                id VARCHAR(255) NOT NULL,
                url VARCHAR(500) NOT NULL,
                secret VARCHAR(120) NOT NULL,
                subscribed_types JSON NOT NULL,
                status VARCHAR(20) NOT NULL,
                consecutive_failures INT NOT NULL,
                disabled_reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE integration_webhook_delivery (
                id VARCHAR(255) NOT NULL,
                endpoint_id VARCHAR(255) NOT NULL,
                event_id VARCHAR(40) NOT NULL,
                event_type VARCHAR(60) NOT NULL,
                payload JSON NOT NULL,
                attempt INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                response_status INT DEFAULT NULL,
                response_body LONGTEXT DEFAULT NULL,
                last_error LONGTEXT DEFAULT NULL,
                next_retry_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                INDEX idx_webhook_delivery_due (status, next_retry_at),
                INDEX idx_webhook_delivery_event (event_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE integration_webhook_delivery
                ADD CONSTRAINT FK_integration_webhook_delivery_endpoint
                FOREIGN KEY (endpoint_id) REFERENCES integration_webhook_endpoint (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_webhook_delivery DROP FOREIGN KEY FK_integration_webhook_delivery_endpoint');
        $this->addSql('DROP TABLE integration_webhook_delivery');
        $this->addSql('DROP TABLE integration_webhook_endpoint');
    }
}
