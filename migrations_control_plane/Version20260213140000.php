<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscriptions and payment_events tables for payment gateway integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS subscriptions (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                account_id INT UNSIGNED NOT NULL,
                plan VARCHAR(50) NOT NULL,
                gateway VARCHAR(20) NOT NULL COMMENT 'paypal, mercadopago',
                gateway_subscription_id VARCHAR(255) DEFAULT NULL,
                gateway_customer_id VARCHAR(255) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, active, past_due, cancelled, suspended',
                current_period_start DATETIME DEFAULT NULL,
                current_period_end DATETIME DEFAULT NULL,
                grace_period_days INT UNSIGNED NOT NULL DEFAULT 5,
                cancelled_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX IDX_SUBSCRIPTIONS_TENANT (tenant_id),
                INDEX IDX_SUBSCRIPTIONS_ACCOUNT (account_id),
                INDEX IDX_SUBSCRIPTIONS_STATUS (status),
                INDEX IDX_SUBSCRIPTIONS_GATEWAY_SUB (gateway, gateway_subscription_id),
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payment_events (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                subscription_id INT UNSIGNED DEFAULT NULL,
                tenant_id INT UNSIGNED DEFAULT NULL,
                gateway VARCHAR(20) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                gateway_event_id VARCHAR(255) NOT NULL,
                payload JSON NOT NULL,
                processed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_PAYMENT_EVENT_IDEMPOTENT (gateway, gateway_event_id),
                INDEX IDX_PAYMENT_EVENTS_SUBSCRIPTION (subscription_id),
                INDEX IDX_PAYMENT_EVENTS_TENANT (tenant_id),
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS payment_events');
        $this->addSql('DROP TABLE IF EXISTS subscriptions');
    }
}
