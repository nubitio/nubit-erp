<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Treasury foundation (ADR-021, docs/architecture/treasury-technical-prd.md).
 *
 * - treasury_account: a place money sits (cash register, bank account, wallet) or the per-branch
 *   suspense account that catches movements whose payment method has no account yet.
 * - treasury_movement: append-only ledger of signed amounts. Nothing updates or deletes a row; a
 *   correction is a new row with the opposite sign pointing at the original (ADR-003).
 * - treasury_payment_method_account: default account per branch, payment method and currency (a Yape
 *   payment in USD needs a USD account). Cash has no row, its account is the user's open cash session.
 *
 * Cash drawers keep their own ledger (cash_movements); see D-A of the PRD. Grants the new
 * treasury.* permissions to the administrator role only. The super administrator holds every
 * permission implicitly.
 */
final class Version20261118000000_treasury_foundation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the treasury account, movement and payment-method mapping tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE treasury_account (
                id CHAR(36) NOT NULL,
                kind VARCHAR(20) NOT NULL,
                name VARCHAR(120) NOT NULL,
                currency CHAR(3) NOT NULL,
                branch_id CHAR(36) DEFAULT NULL,
                cash_register_id INT DEFAULT NULL,
                bank_account_id VARCHAR(36) DEFAULT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_treasury_account_cash_register (cash_register_id),
                UNIQUE INDEX uniq_treasury_account_bank_account (bank_account_id),
                INDEX idx_treasury_account_kind (kind, active),
                INDEX idx_treasury_account_branch (branch_id, kind, currency),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE treasury_movement (
                id CHAR(36) NOT NULL,
                account_id CHAR(36) NOT NULL,
                signed_amount DECIMAL(14, 2) NOT NULL,
                currency CHAR(3) NOT NULL,
                occurred_on DATE NOT NULL,
                occurred_at DATETIME NOT NULL,
                source_type VARCHAR(32) NOT NULL,
                source_id VARCHAR(64) NOT NULL,
                leg VARCHAR(8) NOT NULL DEFAULT 'main',
                party_type VARCHAR(20) DEFAULT NULL,
                party_id VARCHAR(64) DEFAULT NULL,
                reference VARCHAR(120) DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                transfer_id CHAR(36) DEFAULT NULL,
                corrects_movement_id CHAR(36) DEFAULT NULL,
                origin VARCHAR(10) NOT NULL DEFAULT 'live',
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_treasury_movement_source (source_type, source_id, leg),
                INDEX idx_treasury_movement_account (account_id, occurred_at),
                INDEX idx_treasury_movement_transfer (transfer_id),
                INDEX idx_treasury_movement_corrects (corrects_movement_id),
                PRIMARY KEY(id),
                CONSTRAINT chk_treasury_movement_nonzero CHECK (signed_amount <> 0),
                CONSTRAINT fk_treasury_movement_account FOREIGN KEY (account_id) REFERENCES treasury_account (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE treasury_payment_method_account (
                branch_id CHAR(36) NOT NULL,
                payment_method_id INT NOT NULL,
                currency CHAR(3) NOT NULL,
                account_id CHAR(36) NOT NULL,
                PRIMARY KEY(branch_id, payment_method_id, currency),
                INDEX idx_treasury_pma_account (account_id),
                CONSTRAINT fk_treasury_pma_account FOREIGN KEY (account_id) REFERENCES treasury_account (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        foreach (['treasury.view', 'treasury.manage', 'treasury.transfer'] as $permission) {
            $this->addSql(
                "INSERT IGNORE INTO role_permission (role_id, permission) SELECT 2, '{$permission}' WHERE EXISTS (SELECT 1 FROM rol WHERE rol_id = 2)",
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission IN ('treasury.view', 'treasury.manage', 'treasury.transfer')");
        $this->addSql('DROP TABLE treasury_payment_method_account');
        $this->addSql('DROP TABLE treasury_movement');
        $this->addSql('DROP TABLE treasury_account');
    }
}
