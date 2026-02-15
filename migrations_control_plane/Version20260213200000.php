<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant notification targets and deliveries tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tenant_notification_targets (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                tenant_id INT NOT NULL,
                event_key VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL,
                severity VARCHAR(16) NOT NULL DEFAULT 'info',
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                recipients_json JSON DEFAULT NULL,
                config_json JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_TENANT_EVENT_CHANNEL (tenant_id, event_key, channel),
                INDEX IDX_TNT_TARGET_TENANT (tenant_id),
                INDEX IDX_TNT_TARGET_EVENT (event_key),
                INDEX IDX_TNT_TARGET_ENABLED (is_enabled),
                PRIMARY KEY(id),
                CONSTRAINT FK_TNT_TARGET_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tenant_notification_deliveries (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                tenant_id INT NOT NULL,
                event_key VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL,
                severity VARCHAR(16) NOT NULL DEFAULT 'info',
                status VARCHAR(16) NOT NULL,
                recipients_json JSON DEFAULT NULL,
                payload_json JSON DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_at DATETIME DEFAULT NULL,
                INDEX IDX_TNT_DELIVERY_TENANT (tenant_id),
                INDEX IDX_TNT_DELIVERY_EVENT (event_key),
                INDEX IDX_TNT_DELIVERY_STATUS (status),
                INDEX IDX_TNT_DELIVERY_CREATED (created_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_TNT_DELIVERY_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenant_notification_deliveries');
        $this->addSql('DROP TABLE IF EXISTS tenant_notification_targets');
    }
}
