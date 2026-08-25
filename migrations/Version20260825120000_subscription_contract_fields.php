<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825120000_subscription_contract_fields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add service reference, invoice description, agreed amount and end date to customer subscriptions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE customer_subscription
                ADD service_reference VARCHAR(255) DEFAULT NULL,
                ADD custom_description VARCHAR(255) DEFAULT NULL,
                ADD agreed_amount NUMERIC(10, 2) DEFAULT NULL,
                ADD end_date DATE DEFAULT NULL
            SQL);
        $this->addSql(
            'CREATE INDEX idx_customer_subscription_active_end ON customer_subscription (status, end_date)',
        );
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_subscription_run (
                id CHAR(36) NOT NULL COMMENT 'UUID',
                subscription_id CHAR(36) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                source VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                sale_id CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                UNIQUE INDEX uq_subscription_run_period (subscription_id, period_start, period_end),
                INDEX idx_subscription_run_created (created_at),
                INDEX IDX_SUBSCRIPTION_RUN_SUBSCRIPTION (subscription_id),
                CONSTRAINT FK_SUBSCRIPTION_RUN_SUBSCRIPTION
                    FOREIGN KEY (subscription_id) REFERENCES customer_subscription (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_subscription_line (
                id CHAR(36) NOT NULL COMMENT 'UUID',
                subscription_id CHAR(36) NOT NULL,
                product_id VARCHAR(255) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                quantity NUMERIC(12, 3) NOT NULL,
                unit_price NUMERIC(12, 4) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id),
                INDEX idx_subscription_line_subscription_sort (subscription_id, sort_order),
                INDEX IDX_SUBSCRIPTION_LINE_PRODUCT (product_id),
                CONSTRAINT FK_SUBSCRIPTION_LINE_SUBSCRIPTION
                    FOREIGN KEY (subscription_id) REFERENCES customer_subscription (id) ON DELETE CASCADE,
                CONSTRAINT FK_SUBSCRIPTION_LINE_PRODUCT
                    FOREIGN KEY (product_id) REFERENCES producto (producto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(
            'INSERT IGNORE INTO role_permission (role_id, permission) SELECT rol_id, ? FROM rol WHERE rol_id IN (1, 2)',
            ['subscriptions.generate'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'subscriptions.generate'");
        $this->addSql('DROP TABLE customer_subscription_line');
        $this->addSql('DROP TABLE customer_subscription_run');
        $this->addSql('DROP INDEX idx_customer_subscription_active_end ON customer_subscription');
        $this->addSql(<<<'SQL'
            ALTER TABLE customer_subscription
                DROP service_reference,
                DROP custom_description,
                DROP agreed_amount,
                DROP end_date
            SQL);
    }
}
