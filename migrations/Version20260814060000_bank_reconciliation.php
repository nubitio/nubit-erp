<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814060000_bank_reconciliation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bank reconciliation: bank accounts, imported statement lines, and matches '
            . 'linking them to existing supplier payments, sale payments, or cash movements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_account (
                id CHAR(36) NOT NULL,
                bank VARCHAR(100) NOT NULL,
                alias VARCHAR(100) NOT NULL,
                account_number VARCHAR(50) NOT NULL,
                cci VARCHAR(50) DEFAULT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_bank_account_active (active),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE bank_statement_import (
                id CHAR(36) NOT NULL,
                bank_account_id CHAR(36) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                bank_profile VARCHAR(30) NOT NULL,
                status VARCHAR(20) DEFAULT 'PROCESSING' NOT NULL,
                total_rows INT DEFAULT 0 NOT NULL,
                imported_rows INT DEFAULT 0 NOT NULL,
                error_rows INT DEFAULT 0 NOT NULL,
                errors JSON DEFAULT NULL,
                created_by INT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_BANK_STATEMENT_IMPORT_ACCOUNT (bank_account_id),
                INDEX IDX_BANK_STATEMENT_IMPORT_CREATED_BY (created_by),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE bank_statement_line (
                id BIGINT AUTO_INCREMENT NOT NULL,
                bank_account_id CHAR(36) NOT NULL,
                import_batch_id CHAR(36) NOT NULL,
                transaction_date DATE NOT NULL,
                amount NUMERIC(12, 2) NOT NULL,
                glosa VARCHAR(255) NOT NULL,
                reference VARCHAR(100) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'UNMATCHED' NOT NULL,
                row_no INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_bank_statement_line_account_date (bank_account_id, transaction_date),
                INDEX idx_bank_statement_line_status (status),
                INDEX idx_bank_statement_line_batch (import_batch_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE bank_reconciliation_match (
                id INT AUTO_INCREMENT NOT NULL,
                bank_statement_line_id BIGINT NOT NULL,
                supplier_payment_id INT DEFAULT NULL,
                sale_payment_id BIGINT DEFAULT NULL,
                cash_movement_id INT DEFAULT NULL,
                match_type VARCHAR(20) NOT NULL,
                matched_at DATETIME NOT NULL,
                matched_by INT NOT NULL,
                UNIQUE INDEX UNIQ_BRM_LINE (bank_statement_line_id),
                INDEX idx_brm_supplier_payment (supplier_payment_id),
                INDEX idx_brm_sale_payment (sale_payment_id),
                INDEX idx_brm_cash_movement (cash_movement_id),
                INDEX IDX_BRM_MATCHED_BY (matched_by),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE bank_statement_import ADD CONSTRAINT FK_BANK_STATEMENT_IMPORT_ACCOUNT FOREIGN KEY (bank_account_id) REFERENCES bank_account (id)');
        $this->addSql('ALTER TABLE bank_statement_import ADD CONSTRAINT FK_BANK_STATEMENT_IMPORT_CREATED_BY FOREIGN KEY (created_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE bank_statement_line ADD CONSTRAINT FK_BANK_STATEMENT_LINE_ACCOUNT FOREIGN KEY (bank_account_id) REFERENCES bank_account (id)');
        $this->addSql('ALTER TABLE bank_statement_line ADD CONSTRAINT FK_BANK_STATEMENT_LINE_BATCH FOREIGN KEY (import_batch_id) REFERENCES bank_statement_import (id)');
        $this->addSql('ALTER TABLE bank_reconciliation_match ADD CONSTRAINT FK_BRM_LINE FOREIGN KEY (bank_statement_line_id) REFERENCES bank_statement_line (id)');
        $this->addSql('ALTER TABLE bank_reconciliation_match ADD CONSTRAINT FK_BRM_SUPPLIER_PAYMENT FOREIGN KEY (supplier_payment_id) REFERENCES supplier_payment (id)');
        $this->addSql('ALTER TABLE bank_reconciliation_match ADD CONSTRAINT FK_BRM_SALE_PAYMENT FOREIGN KEY (sale_payment_id) REFERENCES sale_payments (id)');
        $this->addSql('ALTER TABLE bank_reconciliation_match ADD CONSTRAINT FK_BRM_CASH_MOVEMENT FOREIGN KEY (cash_movement_id) REFERENCES cash_movement (id)');
        $this->addSql('ALTER TABLE bank_reconciliation_match ADD CONSTRAINT FK_BRM_MATCHED_BY FOREIGN KEY (matched_by) REFERENCES usuario (usuario_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP FOREIGN KEY FK_BRM_LINE');
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP FOREIGN KEY FK_BRM_SUPPLIER_PAYMENT');
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP FOREIGN KEY FK_BRM_SALE_PAYMENT');
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP FOREIGN KEY FK_BRM_CASH_MOVEMENT');
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP FOREIGN KEY FK_BRM_MATCHED_BY');
        $this->addSql('ALTER TABLE bank_statement_line DROP FOREIGN KEY FK_BANK_STATEMENT_LINE_ACCOUNT');
        $this->addSql('ALTER TABLE bank_statement_line DROP FOREIGN KEY FK_BANK_STATEMENT_LINE_BATCH');
        $this->addSql('ALTER TABLE bank_statement_import DROP FOREIGN KEY FK_BANK_STATEMENT_IMPORT_ACCOUNT');
        $this->addSql('ALTER TABLE bank_statement_import DROP FOREIGN KEY FK_BANK_STATEMENT_IMPORT_CREATED_BY');
        $this->addSql('DROP TABLE bank_reconciliation_match');
        $this->addSql('DROP TABLE bank_statement_line');
        $this->addSql('DROP TABLE bank_statement_import');
        $this->addSql('DROP TABLE bank_account');
    }
}
