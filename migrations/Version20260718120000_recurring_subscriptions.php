<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718120000_recurring_subscriptions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurring billing: subscription_plan, customer_subscription tables and due_date/'
            . 'customer_subscription_id on sales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE subscription_plan (
                id CHAR(36) NOT NULL COMMENT 'UUID',
                product_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                billing_cycle VARCHAR(10) NOT NULL DEFAULT 'MONTHLY',
                amount NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
                payment_term_days INT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                branch_id VARCHAR(255) NOT NULL,
                warehouse_id VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY(id),
                INDEX IDX_SUBSCRIPTION_PLAN_PRODUCT (product_id),
                INDEX IDX_SUBSCRIPTION_PLAN_BRANCH (branch_id),
                INDEX IDX_SUBSCRIPTION_PLAN_WAREHOUSE (warehouse_id),
                CONSTRAINT FK_SUBSCRIPTION_PLAN_PRODUCT FOREIGN KEY (product_id) REFERENCES producto (producto_id),
                CONSTRAINT FK_SUBSCRIPTION_PLAN_BRANCH FOREIGN KEY (branch_id) REFERENCES local (local_id),
                CONSTRAINT FK_SUBSCRIPTION_PLAN_WAREHOUSE FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE customer_subscription (
                id CHAR(36) NOT NULL COMMENT 'UUID',
                customer_id INT NOT NULL,
                plan_id VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                start_date DATE NOT NULL,
                current_period_start DATE DEFAULT NULL,
                current_period_end DATE DEFAULT NULL,
                next_billing_date DATE NOT NULL,
                auto_renew TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY(id),
                INDEX IDX_CUSTOMER_SUBSCRIPTION_CUSTOMER (customer_id),
                INDEX IDX_CUSTOMER_SUBSCRIPTION_PLAN (plan_id),
                INDEX idx_customer_subscription_status_next_billing (status, next_billing_date),
                CONSTRAINT FK_CUSTOMER_SUBSCRIPTION_CUSTOMER FOREIGN KEY (customer_id) REFERENCES cliente (id),
                CONSTRAINT FK_CUSTOMER_SUBSCRIPTION_PLAN FOREIGN KEY (plan_id) REFERENCES subscription_plan (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql('ALTER TABLE tenant_settings ADD default_payment_term_days INT DEFAULT 15 NOT NULL');

        $this->addSql(
            'ALTER TABLE sales ADD due_date DATE DEFAULT NULL, '
            . 'ADD customer_subscription_id VARCHAR(255) DEFAULT NULL',
        );
        $this->addSql('CREATE INDEX idx_sales_due_date ON sales (due_date)');
        $this->addSql('CREATE INDEX IDX_SALES_CUSTOMER_SUBSCRIPTION ON sales (customer_subscription_id)');
        $this->addSql(
            'ALTER TABLE sales ADD CONSTRAINT FK_SALES_CUSTOMER_SUBSCRIPTION '
            . 'FOREIGN KEY (customer_subscription_id) REFERENCES customer_subscription (id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY FK_SALES_CUSTOMER_SUBSCRIPTION');
        $this->addSql('DROP INDEX IDX_SALES_CUSTOMER_SUBSCRIPTION ON sales');
        $this->addSql('DROP INDEX idx_sales_due_date ON sales');
        $this->addSql('ALTER TABLE sales DROP COLUMN due_date, DROP COLUMN customer_subscription_id');
        $this->addSql('ALTER TABLE tenant_settings DROP COLUMN default_payment_term_days');
        $this->addSql('DROP TABLE customer_subscription');
        $this->addSql('DROP TABLE subscription_plan');
    }
}
