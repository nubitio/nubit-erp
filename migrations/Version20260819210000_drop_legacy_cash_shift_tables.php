<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819210000_drop_legacy_cash_shift_tables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy cash_movement (singular) and cash_shift tables. Nothing has written to '
            . 'either since the CashRegister v2 cutover (Version20260627000001); every remaining reader '
            . '(GET /api/cash-movements collection, BankReconciliation cash-movement matching, the AI '
            . 'assistant cash-register tool) was repointed to the v2 cash_movements/cash_sessions tables '
            . 'first. See docs/adr/ADR-004-cashregister-strangler-fig-decisions.md\'s amendment. '
            . 'IRREVERSIBLE: down() recreates empty tables with the original schema, not the data.';
    }

    public function up(Schema $schema): void
    {
        // Every statement here is guarded by an information_schema check
        // rather than run unconditionally: MySQL/MariaDB DDL auto-commits
        // regardless of Doctrine's migration "transaction", so a partial
        // failure here (as happened once while authoring this migration —
        // cash_sessions' FK drop and cash_movement's DROP TABLE had already
        // taken effect before cash_shift's DROP TABLE failed) leaves real,
        // permanent partial progress that doctrine_migration_versions
        // doesn't know about. Re-running must be a safe no-op for whatever
        // already happened.

        // cash_sessions.legacy_cash_shift_id keeps a live lookup FK to
        // cash_shift (see CashSessionMovementsProvider's fallback resolve).
        // The column and its data stay — only the constraint blocking the
        // drop below is removed.
        if ($this->fkExists('cash_sessions', 'fk_cash_sessions_legacy_cash_shift')) {
            $this->addSql('ALTER TABLE cash_sessions DROP FOREIGN KEY fk_cash_sessions_legacy_cash_shift');
        }

        // cash_movement is the child (cash_shift_id FK) — must go first.
        if ($this->tableExists('cash_movement')) {
            $this->addSql('DROP TABLE cash_movement');
        }
        if ($this->tableExists('cash_shift')) {
            $this->addSql('DROP TABLE cash_shift');
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );
    }

    private function fkExists(string $table, string $constraint): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
            [$table, $constraint],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cash_shift (
                id INT AUTO_INCREMENT NOT NULL,
                cash_register_id INT NOT NULL,
                opened_by INT NOT NULL,
                closed_by INT DEFAULT NULL,
                status VARCHAR(255) NOT NULL,
                opening_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
                closing_balance DECIMAL(10,2) DEFAULT NULL,
                opened_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                expected_amount DECIMAL(10,2) DEFAULT NULL,
                counted_amount DECIMAL(10,2) DEFAULT NULL,
                difference DECIMAL(10,2) DEFAULT NULL,
                note LONGTEXT DEFAULT NULL,
                branch_id VARCHAR(255) DEFAULT NULL,
                INDEX IDX_9635A069A917CC69 (cash_register_id),
                INDEX IDX_9635A069DE97F77A (opened_by),
                INDEX IDX_9635A06988F6E01 (closed_by),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE cash_movement (
                id INT AUTO_INCREMENT NOT NULL,
                cash_shift_id INT NOT NULL,
                category_id INT DEFAULT NULL,
                type VARCHAR(255) NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                description VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                branch_id VARCHAR(255) DEFAULT NULL,
                INDEX IDX_965E643AD9F34DC3 (cash_shift_id),
                INDEX IDX_965E643A12469DE2 (category_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_9635A069A917CC69 FOREIGN KEY (cash_register_id) REFERENCES cash_register (id)');
        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_9635A069DE97F77A FOREIGN KEY (opened_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_9635A06988F6E01 FOREIGN KEY (closed_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643AD9F34DC3 FOREIGN KEY (cash_shift_id) REFERENCES cash_shift (id)');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643A12469DE2 FOREIGN KEY (category_id) REFERENCES cash_movement_category (id)');
        $this->addSql('ALTER TABLE cash_sessions ADD CONSTRAINT fk_cash_sessions_legacy_cash_shift FOREIGN KEY (legacy_cash_shift_id) REFERENCES cash_shift (id)');
    }
}
