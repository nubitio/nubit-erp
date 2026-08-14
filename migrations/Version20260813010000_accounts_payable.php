<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813010000_accounts_payable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounts payable: supplier bills with due date/balance, payments against them, '
            . 'and an expense category catalog for non-inventory supplier costs (e.g. bank fees)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE expense_category (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                is_system TINYINT(1) DEFAULT 1 NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                UNIQUE INDEX UNIQ_EXPENSE_CATEGORY_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_bill (
                id CHAR(36) NOT NULL,
                supplier_id CHAR(36) NOT NULL,
                branch_id CHAR(36) NOT NULL,
                purchase_id CHAR(36) DEFAULT NULL,
                category_id INT NOT NULL,
                document_type VARCHAR(50) DEFAULT NULL,
                series VARCHAR(20) DEFAULT NULL,
                number VARCHAR(20) DEFAULT NULL,
                issue_date DATE NOT NULL,
                due_date DATE NOT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                amount NUMERIC(12, 2) NOT NULL,
                paid_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                balance_due NUMERIC(12, 2) NOT NULL,
                status VARCHAR(20) DEFAULT 'PENDING' NOT NULL,
                note LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_supplier_bill_status_due_date (status, due_date),
                INDEX idx_supplier_bill_supplier (supplier_id),
                INDEX IDX_SUPPLIER_BILL_BRANCH (branch_id),
                INDEX IDX_SUPPLIER_BILL_PURCHASE (purchase_id),
                INDEX IDX_SUPPLIER_BILL_CATEGORY (category_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_payment (
                id INT AUTO_INCREMENT NOT NULL,
                supplier_bill_id CHAR(36) NOT NULL,
                payment_method_id INT NOT NULL,
                amount NUMERIC(12, 2) NOT NULL,
                paid_at DATE NOT NULL,
                reference VARCHAR(100) DEFAULT NULL,
                created_by INT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_supplier_payment_bill (supplier_bill_id),
                INDEX IDX_SUPPLIER_PAYMENT_METHOD (payment_method_id),
                INDEX IDX_SUPPLIER_PAYMENT_CREATED_BY (created_by),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE supplier_bill ADD CONSTRAINT FK_SUPPLIER_BILL_SUPPLIER FOREIGN KEY (supplier_id) REFERENCES proveedor (proveedor_id)');
        $this->addSql('ALTER TABLE supplier_bill ADD CONSTRAINT FK_SUPPLIER_BILL_BRANCH FOREIGN KEY (branch_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE supplier_bill ADD CONSTRAINT FK_SUPPLIER_BILL_PURCHASE FOREIGN KEY (purchase_id) REFERENCES goods_receipts (id)');
        $this->addSql('ALTER TABLE supplier_bill ADD CONSTRAINT FK_SUPPLIER_BILL_CATEGORY FOREIGN KEY (category_id) REFERENCES expense_category (id)');
        $this->addSql('ALTER TABLE supplier_payment ADD CONSTRAINT FK_SUPPLIER_PAYMENT_BILL FOREIGN KEY (supplier_bill_id) REFERENCES supplier_bill (id)');
        $this->addSql('ALTER TABLE supplier_payment ADD CONSTRAINT FK_SUPPLIER_PAYMENT_METHOD FOREIGN KEY (payment_method_id) REFERENCES payment_method (id)');
        $this->addSql('ALTER TABLE supplier_payment ADD CONSTRAINT FK_SUPPLIER_PAYMENT_CREATED_BY FOREIGN KEY (created_by) REFERENCES usuario (usuario_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_payment DROP FOREIGN KEY FK_SUPPLIER_PAYMENT_BILL');
        $this->addSql('ALTER TABLE supplier_payment DROP FOREIGN KEY FK_SUPPLIER_PAYMENT_METHOD');
        $this->addSql('ALTER TABLE supplier_payment DROP FOREIGN KEY FK_SUPPLIER_PAYMENT_CREATED_BY');
        $this->addSql('ALTER TABLE supplier_bill DROP FOREIGN KEY FK_SUPPLIER_BILL_SUPPLIER');
        $this->addSql('ALTER TABLE supplier_bill DROP FOREIGN KEY FK_SUPPLIER_BILL_BRANCH');
        $this->addSql('ALTER TABLE supplier_bill DROP FOREIGN KEY FK_SUPPLIER_BILL_PURCHASE');
        $this->addSql('ALTER TABLE supplier_bill DROP FOREIGN KEY FK_SUPPLIER_BILL_CATEGORY');
        $this->addSql('DROP TABLE supplier_payment');
        $this->addSql('DROP TABLE supplier_bill');
        $this->addSql('DROP TABLE expense_category');
    }
}
