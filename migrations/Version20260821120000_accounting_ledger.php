<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821120000_accounting_ledger extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Double-entry chart of accounts and append-only journal for the general ledger.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE chart_of_accounts (
                id CHAR(36) NOT NULL,
                code VARCHAR(16) NOT NULL,
                name VARCHAR(160) NOT NULL,
                type VARCHAR(20) NOT NULL,
                is_header TINYINT(1) NOT NULL DEFAULT 0,
                parent_code VARCHAR(16) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE INDEX uniq_chart_of_accounts_code (code),
                INDEX idx_chart_of_accounts_type (type),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE journal_entry (
                id CHAR(36) NOT NULL,
                entry_no VARCHAR(32) NOT NULL,
                source VARCHAR(32) NOT NULL,
                source_id VARCHAR(64) NOT NULL,
                entry_date DATE NOT NULL,
                description VARCHAR(255) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_journal_entry_source (source, source_id),
                UNIQUE INDEX uniq_journal_entry_no (entry_no),
                INDEX idx_journal_entry_date (entry_date),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE journal_entry_line (
                id CHAR(36) NOT NULL,
                journal_entry_id CHAR(36) NOT NULL,
                account_id CHAR(36) NOT NULL,
                line_no INT NOT NULL,
                debit NUMERIC(14, 2) NOT NULL DEFAULT 0.00,
                credit NUMERIC(14, 2) NOT NULL DEFAULT 0.00,
                memo VARCHAR(255) DEFAULT NULL,
                INDEX idx_journal_line_entry (journal_entry_id),
                INDEX idx_journal_line_account (account_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_journal_line_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entry (id) ON DELETE CASCADE,
                CONSTRAINT fk_journal_line_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $now = 'NOW()';
        $accounts = [
            ['10', 'Efectivo y equivalentes de efectivo', 'ASSET', 1, null],
            ['101', 'Caja', 'ASSET', 0, '10'],
            ['1041', 'Cuentas corrientes', 'ASSET', 0, '10'],
            ['12', 'Cuentas por cobrar comerciales', 'ASSET', 1, null],
            ['121', 'Facturas por cobrar', 'ASSET', 0, '12'],
            ['20', 'Mercaderías', 'ASSET', 1, null],
            ['201', 'Mercaderías', 'ASSET', 0, '20'],
            ['40', 'Tributos por pagar', 'LIABILITY', 1, null],
            ['40111', 'IGV — cuenta propia', 'LIABILITY', 0, '40'],
            ['42', 'Cuentas por pagar comerciales', 'LIABILITY', 1, null],
            ['421', 'Facturas por pagar', 'LIABILITY', 0, '42'],
            ['50', 'Capital', 'EQUITY', 1, null],
            ['501', 'Capital social', 'EQUITY', 0, '50'],
            ['65', 'Otros gastos de gestión', 'EXPENSE', 1, null],
            ['659', 'Otros gastos diversos', 'EXPENSE', 0, '65'],
            ['69', 'Costo de ventas', 'EXPENSE', 1, null],
            ['691', 'Costo de ventas', 'EXPENSE', 0, '69'],
            ['70', 'Ventas', 'INCOME', 1, null],
            ['70111', 'Ventas de mercaderías gravadas', 'INCOME', 0, '70'],
            ['75', 'Otros ingresos de gestión', 'INCOME', 1, null],
            ['759', 'Otros ingresos diversos', 'INCOME', 0, '75'],
        ];

        foreach ($accounts as [$code, $name, $type, $header, $parent]) {
            $parentSql = $parent === null ? 'NULL' : $this->connection->quote($parent);
            $this->addSql(
                "INSERT INTO chart_of_accounts (id, code, name, type, is_header, parent_code, is_active) VALUES (UUID(), {$this->connection->quote($code)}, {$this->connection->quote($name)}, {$this->connection->quote($type)}, {$header}, {$parentSql}, 1)",
            );
        }
        unset($now);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE journal_entry_line DROP FOREIGN KEY fk_journal_line_entry');
        $this->addSql('ALTER TABLE journal_entry_line DROP FOREIGN KEY fk_journal_line_account');
        $this->addSql('DROP TABLE journal_entry_line');
        $this->addSql('DROP TABLE journal_entry');
        $this->addSql('DROP TABLE chart_of_accounts');
    }
}
