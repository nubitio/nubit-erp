<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:schema:normalize:tenants',
    description: 'Apply safe schema normalization across tenants (legacy core low-risk fixes).',
)]
final class TenantSchemaNormalizeCommand extends TenantAwareCommand
{
    private const string NO_DEFAULT = '__NO_DEFAULT__';

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview SQL statements without executing them.');
        $this->addOption('skip-menu-id', null, InputOption::VALUE_NONE, 'Skip menu.id AUTO_INCREMENT normalization.');
        $this->addOption('skip-unidad-code', null, InputOption::VALUE_NONE, 'Skip unidad.code normalization.');
        $this->addOption(
            'normalize-legacy-types',
            null,
            InputOption::VALUE_NONE,
            'Normalize legacy numeric/datetime/nullability drift towards canonical schema.'
        );
        $this->addOption(
            'include-id-types',
            null,
            InputOption::VALUE_NONE,
            'Also normalize legacy identifier column types (char/varchar). Use with caution.'
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $skipMenuId = (bool) $input->getOption('skip-menu-id');
        $skipUnidadCode = (bool) $input->getOption('skip-unidad-code');
        $normalizeLegacyTypes = (bool) $input->getOption('normalize-legacy-types');
        $includeIdTypes = (bool) $input->getOption('include-id-types');

        $statements = [];

        if (!$skipMenuId) {
            $statements = array_merge($statements, $this->buildMenuIdNormalization());
        }
        if (!$skipUnidadCode) {
            $statements = array_merge($statements, $this->buildUnidadCodeNormalization());
        }
        if ($normalizeLegacyTypes) {
            $statements = array_merge($statements, $this->buildLegacyTypeNormalization($includeIdTypes));
        }

        if ($statements === []) {
            $io->success('No normalization changes required for this tenant.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->warning(sprintf('Dry run: %d statements pending.', count($statements)));
            foreach ($statements as $sql) {
                $io->writeln($sql . ';');
            }
            return Command::SUCCESS;
        }

        foreach ($statements as $sql) {
            $this->connection->executeStatement($sql);
        }

        $io->success(sprintf('Normalization applied (%d statements).', count($statements)));
        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function buildMenuIdNormalization(): array
    {
        if (!$this->tableExists('menu') || !$this->columnExists('menu', 'id')) {
            return [];
        }

        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT data_type, is_nullable, extra, column_key
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'menu'
  AND column_name = 'id'
SQL);

        if (!is_array($row)) {
            return [];
        }

        $dataType = strtolower((string) ($row['data_type'] ?? ''));
        $isNullable = strtoupper((string) ($row['is_nullable'] ?? 'YES')) === 'YES';
        $extra = strtolower((string) ($row['extra'] ?? ''));
        $columnKey = strtoupper((string) ($row['column_key'] ?? ''));

        if (!in_array($dataType, ['int', 'integer', 'bigint'], true)) {
            return [];
        }

        if (str_contains($extra, 'auto_increment') && !$isNullable && $columnKey !== '') {
            return [];
        }

        $typeSql = in_array($dataType, ['bigint'], true) ? 'BIGINT' : 'INT';
        $statements = [];

        if ($columnKey === '') {
            $hasPrimaryKey = (int) $this->connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM information_schema.table_constraints
WHERE table_schema = DATABASE()
  AND table_name = 'menu'
  AND constraint_type = 'PRIMARY KEY'
SQL) > 0;

            if (!$hasPrimaryKey) {
                $statements[] = 'ALTER TABLE menu ADD PRIMARY KEY (id)';
            } else {
                $hasUniqueOnId = (int) $this->connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'menu'
  AND column_name = 'id'
  AND non_unique = 0
SQL) > 0;

                if (!$hasUniqueOnId) {
                    $statements[] = 'ALTER TABLE menu ADD UNIQUE INDEX uq_menu_id_normalize (id)';
                }
            }
        }

        $statements[] = sprintf('ALTER TABLE menu MODIFY id %s NOT NULL AUTO_INCREMENT', $typeSql);

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function buildUnidadCodeNormalization(): array
    {
        if (!$this->tableExists('unidad')) {
            return [];
        }

        $sql = [];
        $hasCode = $this->columnExists('unidad', 'code');
        $hasCodigo = $this->columnExists('unidad', 'codigo');
        $hasName = $this->columnExists('unidad', 'name');

        if (!$hasCode) {
            $sql[] = 'ALTER TABLE unidad ADD code VARCHAR(255) DEFAULT NULL';
        }

        if ($hasCodigo) {
            $sql[] = "UPDATE unidad SET code = NULLIF(TRIM(codigo), '') WHERE code IS NULL";
        }

        if ($hasName) {
            $sql[] = "UPDATE unidad SET code = NULLIF(TRIM(name), '') WHERE code IS NULL";
        }

        $sql[] = "UPDATE unidad SET code = 'NIU' WHERE code IS NULL";

        $meta = $this->connection->fetchAssociative(<<<'SQL'
SELECT is_nullable
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'unidad'
  AND column_name = 'code'
SQL);

        $isNullable = strtoupper((string) ($meta['is_nullable'] ?? 'YES')) === 'YES';
        if ($isNullable || !$hasCode) {
            $sql[] = 'ALTER TABLE unidad MODIFY code VARCHAR(255) NOT NULL';
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    private function buildLegacyTypeNormalization(bool $includeIdTypes): array
    {
        $statements = [];

        foreach ($this->legacyTypeRules($includeIdTypes) as $rule) {
            $statements = array_merge($statements, $this->buildColumnNormalization($rule));
        }

        return $statements;
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return list<string>
     */
    private function buildColumnNormalization(array $rule): array
    {
        $table = (string) ($rule['table'] ?? '');
        $column = (string) ($rule['column'] ?? '');
        if ($table === '' || $column === '') {
            return [];
        }

        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return [];
        }

        $meta = $this->connection->fetchAssociative(<<<'SQL'
SELECT
  data_type,
  column_type,
  is_nullable,
  column_default,
  extra,
  collation_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = :table
  AND column_name = :column
SQL, ['table' => $table, 'column' => $column]);

        if (!is_array($meta)) {
            return [];
        }

        $targetType = strtolower((string) ($rule['type'] ?? ''));
        $targetNullable = (bool) ($rule['nullable'] ?? true);
        $targetDefault = $rule['default'] ?? self::NO_DEFAULT;
        $targetCollation = strtolower((string) ($rule['collation'] ?? ''));

        $currentType = strtolower((string) ($meta['column_type'] ?? ''));
        $currentNullable = strtoupper((string) ($meta['is_nullable'] ?? 'YES')) === 'YES';
        $currentDefault = $this->normalizeDefaultValue($meta['column_default']);
        $currentCollation = strtolower((string) ($meta['collation_name'] ?? ''));

        $defaultChanged = $this->isDefaultChanged($currentDefault, $targetDefault);
        $same = $currentType === $targetType
            && $currentNullable === $targetNullable
            && $currentCollation === $targetCollation
            && !$defaultChanged;

        if ($same) {
            return [];
        }

        $sql = [];
        if (!$targetNullable) {
            $fillExpr = $rule['fill_if_null'] ?? null;
            if ($fillExpr !== null) {
                $sql[] = sprintf(
                    'UPDATE %s SET %s = %s WHERE %s IS NULL',
                    $table,
                    $column,
                    $fillExpr,
                    $column
                );
            }
        }

        $definitionParts = [
            sprintf('ALTER TABLE %s MODIFY %s %s', $table, $column, strtoupper((string) $rule['type'])),
            $targetNullable ? 'NULL' : 'NOT NULL',
        ];

        if ($targetCollation !== '') {
            $definitionParts[] = sprintf('COLLATE %s', $targetCollation);
        }

        if ($targetDefault !== self::NO_DEFAULT) {
            if ($targetDefault === null) {
                $definitionParts[] = 'DEFAULT NULL';
            } else {
                $definitionParts[] = sprintf('DEFAULT %s', $targetDefault);
            }
        }

        $sql[] = implode(' ', $definitionParts);

        return $sql;
    }

    private function normalizeDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return self::NO_DEFAULT;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'current_timestamp()') {
            return 'current_timestamp';
        }
        if ($normalized === 'utc_timestamp()') {
            return 'utc_timestamp';
        }

        return $normalized;
    }

    private function isDefaultChanged(string $currentDefault, mixed $targetDefault): bool
    {
        if ($targetDefault === self::NO_DEFAULT) {
            return $currentDefault !== self::NO_DEFAULT;
        }

        if ($targetDefault === null) {
            return $currentDefault !== self::NO_DEFAULT && $currentDefault !== 'null';
        }

        $target = strtolower(trim((string) $targetDefault));
        if ($target === 'current_timestamp()') {
            $target = 'current_timestamp';
        }

        return $currentDefault !== $target;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legacyTypeRules(bool $includeIdTypes): array
    {
        $rules = [
            ['table' => 'cash_movement', 'column' => 'amount', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'cash_shift', 'column' => 'opening_balance', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'cash_shift', 'column' => 'closing_balance', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
            ['table' => 'cash_shift', 'column' => 'expected_amount', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
            ['table' => 'cash_shift', 'column' => 'counted_amount', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
            ['table' => 'cash_shift', 'column' => 'difference', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
            ['table' => 'comprobante', 'column' => 'created_at', 'type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP', 'fill_if_null' => 'CURRENT_TIMESTAMP'],
            ['table' => 'comprobante', 'column' => 'discount_amount', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'comprobante', 'column' => 'discount_percent', 'type' => 'int(11)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'fill_if_null' => '0'],
            ['table' => 'comprobante', 'column' => 'gravada', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'comprobante', 'column' => 'igv', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'comprobante', 'column' => 'rc', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'comprobante', 'column' => 'subtotal', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'comprobante', 'column' => 'total', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'comprobante', 'column' => 'voided', 'type' => 'int(11)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'fill_if_null' => '0'],
            ['table' => 'movimiento', 'column' => 'created_by', 'type' => 'int(11)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'fill_if_null' => '1'],
            ['table' => 'movimiento', 'column' => 'date_created', 'type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP', 'fill_if_null' => 'CURRENT_TIMESTAMP'],
            ['table' => 'movimiento', 'column' => 'descuento', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
            ['table' => 'movimiento', 'column' => 'has_invoice', 'type' => 'tinyint(1)', 'nullable' => false, 'default' => '0', 'fill_if_null' => '0'],
            ['table' => 'movimiento', 'column' => 'is_consume', 'type' => 'tinyint(1)', 'nullable' => false, 'default' => '0', 'fill_if_null' => '0'],
            ['table' => 'movimiento', 'column' => 'subtotal', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'movimiento', 'column' => 'total', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'movimiento_detalle', 'column' => 'cantidad', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'movimiento_detalle', 'column' => 'precio_total', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'movimiento_detalle', 'column' => 'precio_unitario', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'nota', 'column' => 'total', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'fill_if_null' => '0.00'],
            ['table' => 'nota', 'column' => 'updated_at', 'type' => 'datetime', 'nullable' => false, 'default' => self::NO_DEFAULT, 'fill_if_null' => 'CURRENT_TIMESTAMP'],
            ['table' => 'producto', 'column' => 'precio_compra', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
            ['table' => 'producto', 'column' => 'precio', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'unidad', 'column' => 'ratio', 'type' => 'decimal(10,2)', 'nullable' => false, 'default' => '0.00', 'fill_if_null' => '0.00'],
            ['table' => 'usuario', 'column' => 'rol_id', 'type' => 'int(11)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'fill_if_null' => '1'],
        ];

        if ($includeIdTypes) {
            $rules = array_merge($rules, [
                ['table' => 'almacen', 'column' => 'almacen_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'empresa', 'column' => 'empresa_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'local', 'column' => 'local_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'media', 'column' => 'id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'movimiento', 'column' => 'movimiento_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'nota', 'column' => 'movimiento_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'operacion', 'column' => 'operacion_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'producto', 'column' => 'producto_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'proveedor', 'column' => 'proveedor_id', 'type' => 'varchar(255)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'tipo_comprobante', 'column' => 'codigo', 'type' => 'varchar(2)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
                ['table' => 'cliente', 'column' => 'ruc', 'type' => 'varchar(11)', 'nullable' => false, 'default' => self::NO_DEFAULT, 'collation' => 'utf8mb4_unicode_ci'],
            ]);
        }

        return $rules;
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
