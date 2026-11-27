<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Finance refactoring, phase 1 (T1.1): receivable, instalments, collections, tenders, allocations and
 * adjustments. Purely additive: nothing reads or writes these tables until the tenant switches on
 * `finance.receivables_v2`, and the legacy columns (sales.balance_due, sale_payments, the customer ledger)
 * keep working unchanged. A collection, its tenders and its allocations are never deleted: a correction
 * is a status change plus a new row (ADR-003).
 */
final class Version20261127130000_sales_receivables_v2 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the receivable, instalment, collection, tender, allocation and adjustment tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sales_receivable (
                id CHAR(36) NOT NULL,
                sale_id VARCHAR(36) NOT NULL,
                customer_id INT NOT NULL,
                branch_id VARCHAR(36) DEFAULT NULL,
                currency CHAR(3) NOT NULL,
                total DECIMAL(12, 2) NOT NULL,
                net_pending DECIMAL(12, 2) NOT NULL,
                opened_on DATE NOT NULL,
                origin VARCHAR(12) NOT NULL DEFAULT 'live',
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_sales_receivable_sale (sale_id),
                INDEX idx_sales_receivable_customer (customer_id, currency),
                CONSTRAINT chk_sales_receivable_amounts CHECK (net_pending >= 0 AND net_pending <= total),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sales_receivable_installment (
                id CHAR(36) NOT NULL,
                receivable_id CHAR(36) NOT NULL,
                seq SMALLINT NOT NULL,
                due_date DATE NOT NULL,
                amount DECIMAL(12, 2) NOT NULL,
                settled_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
                adjusted_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
                UNIQUE INDEX uniq_sales_receivable_installment_seq (receivable_id, seq),
                INDEX idx_sales_receivable_installment_due (due_date),
                CONSTRAINT chk_sales_receivable_installment_amounts CHECK (
                    amount > 0 AND settled_amount >= 0 AND adjusted_amount >= 0
                    AND settled_amount + adjusted_amount <= amount
                ),
                CONSTRAINT fk_sales_receivable_installment_receivable
                    FOREIGN KEY (receivable_id) REFERENCES sales_receivable (id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sales_collection (
                id CHAR(36) NOT NULL,
                customer_id INT NOT NULL,
                branch_id VARCHAR(36) DEFAULT NULL,
                currency CHAR(3) NOT NULL,
                received_on DATE NOT NULL,
                total DECIMAL(12, 2) NOT NULL,
                unapplied DECIMAL(12, 2) NOT NULL DEFAULT 0,
                idempotency_key VARCHAR(64) DEFAULT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'confirmed',
                reversed_at DATETIME DEFAULT NULL,
                reversed_by INT DEFAULT NULL,
                created_by INT DEFAULT NULL,
                origin VARCHAR(12) NOT NULL DEFAULT 'live',
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_sales_collection_idempotency (idempotency_key),
                INDEX idx_sales_collection_customer (customer_id, received_on),
                CONSTRAINT chk_sales_collection_amounts CHECK (total > 0 AND unapplied >= 0 AND unapplied <= total),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sales_collection_tender (
                id CHAR(36) NOT NULL,
                collection_id CHAR(36) NOT NULL,
                payment_method_id INT NOT NULL,
                money_account_id CHAR(36) DEFAULT NULL,
                amount DECIMAL(12, 2) NOT NULL,
                reference VARCHAR(120) DEFAULT NULL,
                INDEX idx_sales_collection_tender_collection (collection_id),
                CONSTRAINT chk_sales_collection_tender_amount CHECK (amount > 0),
                CONSTRAINT fk_sales_collection_tender_collection FOREIGN KEY (collection_id) REFERENCES sales_collection (id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sales_collection_allocation (
                id CHAR(36) NOT NULL,
                collection_id CHAR(36) NOT NULL,
                installment_id CHAR(36) NOT NULL,
                amount DECIMAL(12, 2) NOT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'active',
                applied_on DATE NOT NULL,
                reversed_at DATETIME DEFAULT NULL,
                INDEX idx_sales_collection_allocation_collection (collection_id),
                INDEX idx_sales_collection_allocation_installment (installment_id, status),
                CONSTRAINT chk_sales_collection_allocation_amount CHECK (amount > 0),
                CONSTRAINT fk_sales_collection_allocation_collection FOREIGN KEY (collection_id) REFERENCES sales_collection (id),
                CONSTRAINT fk_sales_collection_allocation_installment FOREIGN KEY (installment_id) REFERENCES sales_receivable_installment (id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sales_receivable_adjustment (
                id CHAR(36) NOT NULL,
                receivable_id CHAR(36) NOT NULL,
                kind VARCHAR(24) NOT NULL,
                amount DECIMAL(12, 2) NOT NULL,
                occurred_on DATE NOT NULL,
                tax_document_id VARCHAR(36) DEFAULT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_sales_receivable_adjustment_receivable (receivable_id, occurred_on),
                CONSTRAINT chk_sales_receivable_adjustment_amount CHECK (amount > 0),
                CONSTRAINT fk_sales_receivable_adjustment_receivable FOREIGN KEY (receivable_id) REFERENCES sales_receivable (id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'sales_receivable_adjustment',
            'sales_collection_allocation',
            'sales_collection_tender',
            'sales_collection',
            'sales_receivable_installment',
            'sales_receivable',
        ] as $table) {
            $this->addSql(sprintf('DROP TABLE %s', $table));
        }
    }
}
