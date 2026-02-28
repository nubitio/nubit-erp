<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228235000_normalize_unidad_code extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize unidad schema: enforce canonical unidad.code column for all tenants.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('unidad')) {
            return;
        }

        $hasCode = $this->columnExists('unidad', 'code');
        $hasCodigo = $this->columnExists('unidad', 'codigo');
        $hasName = $this->columnExists('unidad', 'name');

        if (!$hasCode) {
            $this->addSql('ALTER TABLE unidad ADD code VARCHAR(255) DEFAULT NULL');

            if ($hasCodigo) {
                $this->addSql("UPDATE unidad SET code = NULLIF(TRIM(codigo), '') WHERE code IS NULL");
            }

            if ($hasName) {
                $this->addSql("UPDATE unidad SET code = NULLIF(TRIM(name), '') WHERE code IS NULL");
            }

            $this->addSql("UPDATE unidad SET code = 'NIU' WHERE code IS NULL");
            $this->addSql('ALTER TABLE unidad MODIFY code VARCHAR(255) NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally no-op: dropping code could break runtime mappings.
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table]
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $table, 'column' => $column]
        ) > 0;
    }
}
