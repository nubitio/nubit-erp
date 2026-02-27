<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227180000_enterprise_guardrails extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enterprise DB guardrails: branch-warehouse/register consistency, one open cash session per register, stronger voucher/document uniqueness.';
    }

    public function up(Schema $schema): void
    {
        // One series per branch and document type.
        $this->createUniqueIndexIfMissing(
            'local_comprobante',
            'uq_local_comprobante_branch_type_series',
            ['local_id', 'tipo_comprobante_id', 'serie']
        );

        // Document uniqueness should be scoped by branch.
        $this->dropIndexIfExists('tax_document', 'UNIQ_TAX_DOCUMENT_NUMBER');
        $this->createUniqueIndexIfMissing(
            'tax_document',
            'uq_tax_document_number_branch',
            ['document_type', 'branch_id', 'series', 'number']
        );

        // Prepare composite references needed for cross-table consistency FKs.
        $this->createUniqueIndexIfMissing('almacen', 'uq_almacen_id_local', ['almacen_id', 'local_id']);
        $this->createUniqueIndexIfMissing('cash_register', 'uq_cash_register_id_branch', ['id', 'branch_id']);
        $this->createUniqueIndexIfMissing('cash_sessions', 'uq_cash_sessions_id_branch', ['id', 'branch_id']);

        // Align existing data before adding strict session/branch foreign keys.
        $this->normalizeSessionBranchReferences();

        // Enforce sales.branch_id == warehouse.local_id and register/session branch alignment.
        $this->addForeignKeyIfMissing(
            'sales',
            'fk_sales_warehouse_branch',
            '(warehouse_id, branch_id)',
            'almacen (almacen_id, local_id)'
        );
        $this->addForeignKeyIfMissing(
            'sales',
            'fk_sales_register_branch',
            '(cash_register_id, branch_id)',
            'cash_register (id, branch_id)'
        );
        $this->addForeignKeyIfMissing(
            'sales',
            'fk_sales_session_branch',
            '(cash_session_id, branch_id)',
            'cash_sessions (id, branch_id)'
        );

        // Enforce returns.branch_id == warehouse.local_id and optional session branch alignment.
        $this->addForeignKeyIfMissing(
            'returns',
            'fk_returns_warehouse_branch',
            '(warehouse_id, branch_id)',
            'almacen (almacen_id, local_id)'
        );
        $this->addForeignKeyIfMissing(
            'returns',
            'fk_returns_session_branch',
            '(cash_session_id, branch_id)',
            'cash_sessions (id, branch_id)'
        );

        // Only one OPEN cash session per register.
        if (!$this->columnExists('cash_sessions', 'open_register_guard')) {
            $this->addSql("ALTER TABLE cash_sessions ADD COLUMN open_register_guard INT GENERATED ALWAYS AS (CASE WHEN status = 'OPEN' THEN cash_register_id ELSE NULL END) STORED");
        }
        $this->createUniqueIndexIfMissing('cash_sessions', 'uq_cash_sessions_one_open_per_register', ['open_register_guard']);
    }

    public function down(Schema $schema): void
    {
        $this->dropIndexIfExists('cash_sessions', 'uq_cash_sessions_one_open_per_register');
        if ($this->columnExists('cash_sessions', 'open_register_guard')) {
            $this->addSql('ALTER TABLE cash_sessions DROP COLUMN open_register_guard');
        }

        $this->dropForeignKeyIfExists('returns', 'fk_returns_session_branch');
        $this->dropForeignKeyIfExists('returns', 'fk_returns_warehouse_branch');

        $this->dropForeignKeyIfExists('sales', 'fk_sales_session_branch');
        $this->dropForeignKeyIfExists('sales', 'fk_sales_register_branch');
        $this->dropForeignKeyIfExists('sales', 'fk_sales_warehouse_branch');

        $this->dropIndexIfExists('cash_sessions', 'uq_cash_sessions_id_branch');
        $this->dropIndexIfExists('cash_register', 'uq_cash_register_id_branch');
        $this->dropIndexIfExists('almacen', 'uq_almacen_id_local');

        $this->dropIndexIfExists('tax_document', 'uq_tax_document_number_branch');
        $this->createUniqueIndexIfMissing(
            'tax_document',
            'UNIQ_TAX_DOCUMENT_NUMBER',
            ['document_type', 'series', 'number']
        );

        $this->dropIndexIfExists('local_comprobante', 'uq_local_comprobante_branch_type_series');
    }

    private function createUniqueIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $columnsSql = implode(', ', $columns);
        $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON %s (%s)', $indexName, $table, $columnsSql));
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraintName,
        string $localColumnsSql,
        string $foreignReferenceSql
    ): void {
        if ($this->foreignKeyExists($table, $constraintName)) {
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
        if (!$this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $constraintName));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        $this->addSql(sprintf('DROP INDEX %s ON %s', $indexName, $table));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $exists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = :table_name
  AND index_name = :index_name
LIMIT 1
SQL,
            [
                'table_name' => $table,
                'index_name' => $indexName,
            ]
        );

        return $exists !== false;
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

    private function columnExists(string $table, string $column): bool
    {
        $exists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = :table_name
  AND column_name = :column_name
LIMIT 1
SQL,
            [
                'table_name' => $table,
                'column_name' => $column,
            ]
        );

        return $exists !== false;
    }

    private function normalizeSessionBranchReferences(): void
    {
        // Ensure each sales branch has a cash register.
        $this->addSql(<<<'SQL'
INSERT INTO cash_register (branch_id, name)
SELECT x.branch_id, 'MIGRATION_AUTO'
FROM (
    SELECT DISTINCT s.branch_id
    FROM sales s
    LEFT JOIN cash_register cr ON cr.branch_id = s.branch_id
    WHERE cr.id IS NULL
) x
SQL);

        // Ensure each branch has at least one cash_session row.
        $this->addSql(<<<'SQL'
INSERT INTO cash_sessions (
    legacy_cash_shift_id,
    cash_register_id,
    branch_id,
    opened_by,
    closed_by,
    status,
    opening_amount,
    expected_amount,
    counted_amount,
    difference_amount,
    opened_at,
    closed_at,
    close_note
)
SELECT
    NULL,
    cr.id,
    cr.branch_id,
    u.usuario_id,
    u.usuario_id,
    'CLOSED',
    0,
    0,
    0,
    0,
    NOW(),
    NOW(),
    'Auto-generated by enterprise guardrails migration'
FROM cash_register cr
CROSS JOIN (SELECT MIN(usuario_id) AS usuario_id FROM usuario) u
LEFT JOIN cash_sessions cs ON cs.branch_id = cr.branch_id
WHERE cs.id IS NULL
  AND u.usuario_id IS NOT NULL
GROUP BY cr.id, cr.branch_id, u.usuario_id
SQL);

        // Fix sales.session -> cash_sessions by branch:
        // 1) direct id match
        // 2) legacy shift id match
        // 3) register+branch fallback
        // 4) branch fallback
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
  AND NOT EXISTS (
      SELECT 1
      FROM cash_sessions v
      WHERE v.id = s.cash_session_id
        AND v.branch_id = s.branch_id
  )
SQL);

        // Last-resort cleanup: unresolved session references must be nullable
        // so strict branch/session FK can be added safely.
        $this->addSql(<<<'SQL'
UPDATE sales s
SET s.cash_session_id = NULL
WHERE s.cash_session_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM cash_sessions v
      WHERE v.id = s.cash_session_id
        AND v.branch_id = s.branch_id
  )
SQL);

        // Fix returns.session with branch-safe fallback.
        $this->addSql(<<<'SQL'
UPDATE returns r
INNER JOIN (
    SELECT
        r0.id AS return_id,
        COALESCE(
            (
                SELECT cs1.id
                FROM cash_sessions cs1
                WHERE cs1.id = r0.cash_session_id
                  AND cs1.branch_id = r0.branch_id
                LIMIT 1
            ),
            (
                SELECT cs2.id
                FROM cash_sessions cs2
                WHERE cs2.legacy_cash_shift_id = r0.cash_session_id
                  AND cs2.branch_id = r0.branch_id
                LIMIT 1
            ),
            (
                SELECT cs3.id
                FROM cash_sessions cs3
                WHERE cs3.branch_id = r0.branch_id
                ORDER BY (cs3.status = 'OPEN') DESC, cs3.opened_at DESC
                LIMIT 1
            )
        ) AS target_session_id
    FROM returns r0
) fix ON fix.return_id = r.id
SET r.cash_session_id = fix.target_session_id
WHERE r.cash_session_id IS NOT NULL
  AND fix.target_session_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM cash_sessions v
      WHERE v.id = r.cash_session_id
        AND v.branch_id = r.branch_id
  )
SQL);

        // Same cleanup for returns to avoid FK violations on legacy rows.
        $this->addSql(<<<'SQL'
UPDATE returns r
SET r.cash_session_id = NULL
WHERE r.cash_session_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM cash_sessions v
      WHERE v.id = r.cash_session_id
        AND v.branch_id = r.branch_id
  )
SQL);
    }
}
