<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
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
        $indexes = $this->schemaManager()->listTableIndexes($table);
        $needle = strtolower($indexName);
        foreach ($indexes as $name => $index) {
            if (strtolower((string)$name) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $foreignKeys = $this->schemaManager()->listTableForeignKeys($table);
        $needle = strtolower($constraintName);
        foreach ($foreignKeys as $foreignKey) {
            if (strtolower($foreignKey->getName()) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $columns = $this->schemaManager()->listTableColumns($table);
        $needle = strtolower($column);
        foreach ($columns as $name => $definition) {
            if (strtolower((string)$name) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function schemaManager(): AbstractSchemaManager
    {
        return $this->connection->createSchemaManager();
    }
}
