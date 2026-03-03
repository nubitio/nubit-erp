<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make sales.cash_register_id nullable so that legacy movimiento rows that
 * pre-date the POS cash-register feature (caja_id IS NULL) can be backfilled
 * into the Sales V2 table without requiring a sentinel row.
 *
 * The composite FK fk_sales_register_branch (cash_register_id, branch_id) ->
 * cash_register (id, branch_id) is dropped entirely because MariaDB does not
 * allow composite FKs where one column is nullable.  The single-column FK
 * fk_sales_cash_register already enforces referential integrity for the
 * register, so the composite FK was only providing redundant enforcement.
 * A plain composite index is kept for query performance.
 */
final class Version20260303200000_sales_cash_register_nullable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make sales.cash_register_id nullable (legacy sales without a POS register)';
    }

    public function up(Schema $schema): void
    {
        // Drop both FKs that reference cash_register before modifying the column
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY fk_sales_register_branch');
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY fk_sales_cash_register');
        $this->addSql('DROP INDEX fk_sales_register_branch ON sales');

        // Make the column nullable
        $this->addSql('ALTER TABLE sales MODIFY cash_register_id INT DEFAULT NULL');

        // Re-add the single-column FK with ON DELETE SET NULL
        $this->addSql(
            'ALTER TABLE sales ADD CONSTRAINT fk_sales_cash_register
             FOREIGN KEY (cash_register_id) REFERENCES cash_register (id) ON DELETE SET NULL'
        );

        // Keep a composite index for query performance (but no FK — MariaDB rejects
        // composite FKs that include a nullable column)
        $this->addSql('CREATE INDEX fk_sales_register_branch ON sales (cash_register_id, branch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY fk_sales_cash_register');
        $this->addSql('DROP INDEX fk_sales_register_branch ON sales');

        // Restore NOT NULL — will fail if any row has cash_register_id IS NULL
        $this->addSql('ALTER TABLE sales MODIFY cash_register_id INT NOT NULL');

        $this->addSql(
            'ALTER TABLE sales ADD CONSTRAINT fk_sales_cash_register
             FOREIGN KEY (cash_register_id) REFERENCES cash_register (id)'
        );
        $this->addSql(
            'ALTER TABLE sales ADD CONSTRAINT fk_sales_register_branch
             FOREIGN KEY (cash_register_id, branch_id) REFERENCES cash_register (id, branch_id)'
        );
        $this->addSql('CREATE INDEX fk_sales_register_branch ON sales (cash_register_id, branch_id)');
    }
}
