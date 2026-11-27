<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Finance refactoring, phase 0 (T0.3, T0.10): let the database refuse what the application already rejects.
 *
 *  - a customer-ledger entry is unique per (source, source_id, entry_type), so a retried collection cannot post twice;
 *  - paid_total and balance_due are never negative on sales and supplier bills.
 *
 * Each constraint is added only on a tenant whose data already satisfies it. A tenant with violations keeps
 * running unchanged: `finance:audit` lists the rows and the constraint is added after they are reconciled
 * (a skipped statement is reported, never fatal, so a dirty tenant cannot block a deploy).
 */
final class Version20261127100000_finance_integrity_constraints extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unique customer-ledger source and non-negative balances (only where the data is clean)';
    }

    public function up(Schema $schema): void
    {
        $this->addIfClean(
            'uniq_customer_account_entry_source',
            "SELECT COUNT(*) FROM (SELECT 1 FROM customer_account_entries
                WHERE source IS NOT NULL AND source_id IS NOT NULL
                GROUP BY source, source_id, entry_type HAVING COUNT(*) > 1) d",
            'CREATE UNIQUE INDEX uniq_customer_account_entry_source ON customer_account_entries (source, source_id, entry_type)',
            $this->indexExists('customer_account_entries', 'uniq_customer_account_entry_source'),
        );
        $this->addIfClean(
            'chk_sales_balances_nonnegative',
            'SELECT COUNT(*) FROM sales WHERE balance_due < 0 OR paid_total < 0',
            'ALTER TABLE sales ADD CONSTRAINT chk_sales_balances_nonnegative CHECK (balance_due >= 0 AND paid_total >= 0)',
            $this->constraintExists('sales', 'chk_sales_balances_nonnegative'),
        );
        $this->addIfClean(
            'chk_supplier_bill_balances_nonnegative',
            'SELECT COUNT(*) FROM supplier_bill WHERE balance_due < 0 OR paid_total < 0',
            'ALTER TABLE supplier_bill ADD CONSTRAINT chk_supplier_bill_balances_nonnegative CHECK (balance_due >= 0 AND paid_total >= 0)',
            $this->constraintExists('supplier_bill', 'chk_supplier_bill_balances_nonnegative'),
        );
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('customer_account_entries', 'uniq_customer_account_entry_source')) {
            $this->addSql('DROP INDEX uniq_customer_account_entry_source ON customer_account_entries');
        }
        if ($this->constraintExists('sales', 'chk_sales_balances_nonnegative')) {
            $this->addSql('ALTER TABLE sales DROP CONSTRAINT chk_sales_balances_nonnegative');
        }
        if ($this->constraintExists('supplier_bill', 'chk_supplier_bill_balances_nonnegative')) {
            $this->addSql('ALTER TABLE supplier_bill DROP CONSTRAINT chk_supplier_bill_balances_nonnegative');
        }
    }

    private function addIfClean(string $name, string $violationsSql, string $ddl, bool $alreadyThere): void
    {
        if ($alreadyThere) {
            return;
        }

        $violations = (int) $this->connection->fetchOne($violationsSql);
        if ($violations > 0) {
            $this->write(sprintf(
                '<comment>%s skipped: %d violating row(s). Run finance:audit, reconcile, then add it.</comment>',
                $name,
                $violations,
            ));

            return;
        }

        $this->addSql($ddl);
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index],
        ) > 0;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$table, $constraint],
        ) > 0;
    }
}
