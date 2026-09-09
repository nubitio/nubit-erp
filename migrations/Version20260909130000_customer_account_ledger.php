<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic #367 / #380: the customer current-account layer that sits on top of
 * per-sale debt (`sales.balance_due` + `sale_payments`), without replacing it.
 *
 *  - customer_account_entries: append-only ledger of every movement against a
 *    customer's balance (charge / payment / advance / advance applied / credit
 *    note), each row stamped with the running balance after the movement.
 *  - customer_payment_applications: which sale(s) a payment or an applied
 *    advance settled and by how much — the many-to-many that `sale_payments`
 *    (hard FK to one sale) cannot express.
 *
 * `customer_id` references Contacts' `cliente` and `sale_id` references `sales`;
 * neither gets a DB FK — the cross-context reference stays loose (Sales already
 * reads `cliente` via raw SQL), and it keeps the #384 backfill free of insert
 * ordering constraints. The one FK is internal to this feature.
 */
final class Version20260909130000_customer_account_ledger extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_account_entries and customer_payment_applications (epic #367)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_account_entries (
                id BIGINT AUTO_INCREMENT NOT NULL,
                customer_id INT NOT NULL,
                entry_type VARCHAR(32) NOT NULL,
                occurred_on DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                debit NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                credit NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                running_balance NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                sale_id VARCHAR(255) DEFAULT NULL,
                source VARCHAR(40) DEFAULT NULL,
                source_id VARCHAR(64) DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_customer_account_entry_customer (customer_id, id),
                INDEX idx_customer_account_entry_sale (sale_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE customer_payment_applications (
                id BIGINT AUTO_INCREMENT NOT NULL,
                customer_account_entry_id BIGINT NOT NULL,
                sale_id VARCHAR(255) NOT NULL,
                sale_payment_id BIGINT DEFAULT NULL,
                applied_amount NUMERIC(12, 2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                applied_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_customer_payment_application_entry (customer_account_entry_id),
                INDEX idx_customer_payment_application_sale (sale_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE customer_payment_applications
                ADD CONSTRAINT FK_CUSTOMER_PAYMENT_APPLICATION_ENTRY
                FOREIGN KEY (customer_account_entry_id)
                REFERENCES customer_account_entries (id)
                ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_payment_applications DROP FOREIGN KEY FK_CUSTOMER_PAYMENT_APPLICATION_ENTRY');
        $this->addSql('DROP TABLE customer_payment_applications');
        $this->addSql('DROP TABLE customer_account_entries');
    }
}
