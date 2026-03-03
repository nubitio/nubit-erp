<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303000000_sales_cash_session_nullable extends AbstractMigration
{
    public function getDescription(): string
    {
        // WARNING: down() is lossy when NULL rows exist (see §8 of SLICE-006 spec).
        return 'SLICE-006: make sales.cash_session_id nullable — aligns with SLICE-004 documented NULL behaviour.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('sales')) {
            return;
        }

        // Fix orphaned FK references BEFORE dropping the FK constraint.
        // This resolves the Q2 risk: rows in sales that reference a non-existent
        // cash_sessions.id would cause re-adding the FK to fail. Null them first.
        $this->addSql(<<<'SQL'
            UPDATE sales
            SET cash_session_id = NULL
            WHERE cash_session_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM cash_sessions cs
                  WHERE cs.id = sales.cash_session_id
              )
        SQL);

        $this->dropForeignKeyIfExists('sales', 'fk_sales_cash_session_v2');

        $this->addSql('ALTER TABLE sales MODIFY cash_session_id INT DEFAULT NULL');

        $this->addForeignKeyIfMissing(
            'sales',
            'fk_sales_cash_session_v2',
            '(cash_session_id)',
            'cash_sessions (id)'
        );
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('sales')) {
            return;
        }

        // LOSSY: existing NULL rows receive placeholder value 0 to satisfy NOT NULL.
        // Documented in getDescription(). Rollback in production is not supported
        // once real data has been written with cash_session_id = NULL.
        $this->addSql('UPDATE sales SET cash_session_id = 0 WHERE cash_session_id IS NULL');

        $this->dropForeignKeyIfExists('sales', 'fk_sales_cash_session_v2');

        $this->addSql('ALTER TABLE sales MODIFY cash_session_id INT NOT NULL');

        $this->addForeignKeyIfMissing(
            'sales',
            'fk_sales_cash_session_v2',
            '(cash_session_id)',
            'cash_sessions (id)'
        );
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraintName,
        string $localColumnsSql,
        string $foreignReferenceSql
    ): void {
        if (!$this->tableExists($table) || $this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        $this->addSql(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY %s REFERENCES %s',
            $table,
            $constraintName,
            $localColumnsSql,
            $foreignReferenceSql
        ));
    }

    private function dropForeignKeyIfExists(string $table, string $constraintName): void
    {
        if (!$this->tableExists($table) || !$this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $constraintName));
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $exists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
FROM information_schema.table_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = :table_name
  AND constraint_name = :constraint_name
  AND constraint_type = 'FOREIGN KEY'
LIMIT 1
SQL,
            [
                'table_name' => $table,
                'constraint_name' => $constraintName,
            ]
        );

        return $exists !== false;
    }

    private function tableExists(string $table): bool
    {
        $exists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = :table_name
LIMIT 1
SQL,
            ['table_name' => $table]
        );

        return $exists !== false;
    }
}
