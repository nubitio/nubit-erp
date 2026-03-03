<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Performance optimisation for app:sales:v2:backfill.
 *
 * Adds a composite index on movimiento(tipo_id, almacen_id, branch_id) so that
 * the WHERE m.tipo_id = 2 AND m.almacen_id IS NOT NULL AND m.branch_id IS NOT NULL
 * filter in the backfill command can be resolved with a narrow index range scan
 * instead of a full table scan.
 *
 * Safe to run on production: CREATE INDEX … locks are brief on modern MariaDB
 * (online DDL); the index is idempotent because up() checks before adding.
 */
final class Version20260303100000_backfill_perf_index extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index movimiento(tipo_id, almacen_id, branch_id) for backfill performance.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('movimiento')) {
            return;
        }

        if (!$this->indexExists('movimiento', 'idx_movimiento_tipo_almacen_branch')) {
            $this->addSql(
                'CREATE INDEX idx_movimiento_tipo_almacen_branch ON movimiento (tipo_id, almacen_id, branch_id)'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('movimiento')) {
            return;
        }

        if ($this->indexExists('movimiento', 'idx_movimiento_tipo_almacen_branch')) {
            $this->addSql('DROP INDEX idx_movimiento_tipo_almacen_branch ON movimiento');
        }
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

    private function indexExists(string $table, string $indexName): bool
    {
        $exists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name   = :table_name
  AND index_name   = :index_name
LIMIT 1
SQL,
            [
                'table_name' => $table,
                'index_name' => $indexName,
            ]
        );

        return $exists !== false;
    }
}
