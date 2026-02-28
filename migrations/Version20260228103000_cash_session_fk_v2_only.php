<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228103000_cash_session_fk_v2_only extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1 hardening: align sales/returns cash_session_id to cash_sessions and remove legacy cash_shift foreign keys.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('cash_sessions')) {
            return;
        }

        if ($this->tableExists('sales')) {
            // Normalize existing references to canonical cash_sessions.id.
            $this->addSql(<<<'SQL'
UPDATE sales s
INNER JOIN (
    SELECT
        s0.id AS sale_id,
        COALESCE(
            (
                SELECT cs1.id
                FROM cash_sessions cs1
                WHERE cs1.id = s0.cash_session_id
                  AND cs1.branch_id = s0.branch_id
                LIMIT 1
            ),
            (
                SELECT cs2.id
                FROM cash_sessions cs2
                WHERE cs2.legacy_cash_shift_id = s0.cash_session_id
                  AND cs2.branch_id = s0.branch_id
                LIMIT 1
            ),
            (
                SELECT cs3.id
                FROM cash_sessions cs3
                WHERE cs3.cash_register_id = s0.cash_register_id
                  AND cs3.branch_id = s0.branch_id
                ORDER BY (cs3.status = 'OPEN') DESC, cs3.opened_at DESC
                LIMIT 1
            ),
            (
                SELECT cs4.id
                FROM cash_sessions cs4
                WHERE cs4.branch_id = s0.branch_id
                ORDER BY (cs4.status = 'OPEN') DESC, cs4.opened_at DESC
                LIMIT 1
            )
        ) AS target_session_id
    FROM sales s0
) fix ON fix.sale_id = s.id
SET s.cash_session_id = fix.target_session_id
WHERE fix.target_session_id IS NOT NULL
SQL);
        }

        if ($this->tableExists('returns')) {
            // returns.cash_session_id is nullable, so unresolved rows can be nulled.
            $this->addSql(<<<'SQL'
UPDATE returns r
SET r.cash_session_id = NULL
WHERE r.cash_session_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM cash_sessions cs
      WHERE cs.id = r.cash_session_id
  )
SQL);
        }

        $this->dropForeignKeyIfExists('sales', 'fk_sales_cash_session');
        $this->dropForeignKeyIfExists('returns', 'fk_returns_cash_session');

        $this->addForeignKeyIfMissing(
            'sales',
            'fk_sales_cash_session_v2',
            '(cash_session_id)',
            'cash_sessions (id)'
        );
        $this->addForeignKeyIfMissing(
            'returns',
            'fk_returns_cash_session_v2',
            '(cash_session_id)',
            'cash_sessions (id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->dropForeignKeyIfExists('returns', 'fk_returns_cash_session_v2');
        $this->dropForeignKeyIfExists('sales', 'fk_sales_cash_session_v2');

        if ($this->tableExists('cash_shift')) {
            $this->addForeignKeyIfMissing(
                'sales',
                'fk_sales_cash_session',
                '(cash_session_id)',
                'cash_shift (id)'
            );
            $this->addForeignKeyIfMissing(
                'returns',
                'fk_returns_cash_session',
                '(cash_session_id)',
                'cash_shift (id)'
            );
        }
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

