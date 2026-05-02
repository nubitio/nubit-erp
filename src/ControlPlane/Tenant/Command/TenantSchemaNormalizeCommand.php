<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
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
final class TenantSchemaNormalizeCommand extends PerTenantCommand
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
            'Normalize legacy numeric/datetime/nullability drift towards canonical schema.',
        );
        $this->addOption(
            'include-id-types',
            null,
            InputOption::VALUE_NONE,
            'Also normalize legacy identifier column types (char/varchar). Use with caution.',
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

        foreach ($this->legacyTypeRules() as $rule) {
            $statements = array_merge($statements, $this->buildColumnNormalization($rule));
        }

        if ($includeIdTypes) {
            $statements = array_merge($statements, $this->buildFkAwareIdTypeNormalization());
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
        $targetDefault = array_key_exists('default', $rule) ? $rule['default'] : self::NO_DEFAULT;
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
                    $column,
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
    private function legacyTypeRules(): array
    {
        return [
            $this->rule('cash_movement', 'amount', 'decimal(10,2)', false, '0.00', '0.00'),
            $this->rule('cash_shift', 'opening_balance', 'decimal(10,2)', false, '0.00', '0.00'),
            $this->rule('cash_shift', 'closing_balance', 'decimal(10,2)', true, null),
            $this->rule('cash_shift', 'expected_amount', 'decimal(10,2)', true, null),
            $this->rule('cash_shift', 'counted_amount', 'decimal(10,2)', true, null),
            $this->rule('cash_shift', 'difference', 'decimal(10,2)', true, null),
            $this->rule('producto', 'precio_compra', 'decimal(10,2)', true, null),
            $this->rule('producto', 'precio', 'decimal(10,2)', false, '0.00', '0.00'),
            $this->rule('unidad', 'ratio', 'decimal(10,2)', false, '0.00', '0.00'),
            $this->rule('usuario', 'rol_id', 'int(11)', false, self::NO_DEFAULT, '1'),
            $this->rule('cash_sessions', 'opening_amount', 'decimal(10,2)', false, '0.00', '0.00'),
            $this->rule('cash_sessions', 'expected_amount', 'decimal(10,2)', true, null),
            $this->rule('cash_sessions', 'counted_amount', 'decimal(10,2)', true, null),
            $this->rule('cash_sessions', 'difference', 'decimal(10,2)', true, null),
            $this->rule('cash_movements', 'amount', 'decimal(10,2)', false, '0.00', '0.00'),
        ];
    }

    /**
     * @return list<string>
     */
    private function buildFkAwareIdTypeNormalization(): array
    {
        $idRules = $this->idTypeRules();
        if ($idRules === []) {
            return [];
        }

        /** @var array<string, array{type:string, collation:string}> $targets */
        $targets = [];
        foreach ($idRules as $rule) {
            $key = strtolower($rule['table'] . '.' . $rule['column']);
            if (
                !$this->tableExists($rule['table'])
                || !$this->columnExists($rule['table'], $rule['column'])
            ) {
                continue;
            }

            $targets[$key] = [
                'type' => strtolower((string) $rule['type']),
                'collation' => strtolower((string) $rule['collation']),
            ];
        }

        if ($targets === []) {
            return [];
        }

        $fks = $this->fetchSingleColumnForeignKeys();
        $dropSql = [];
        $recreateSql = [];

        foreach ($fks as $fk) {
            $localKey = strtolower($fk['table_name'] . '.' . $fk['column_name']);
            $refKey = strtolower($fk['referenced_table_name'] . '.' . $fk['referenced_column_name']);

            if (!isset($targets[$localKey]) && !isset($targets[$refKey])) {
                continue;
            }

            $sourceKey = isset($targets[$refKey]) ? $refKey : $localKey;
            $targets[$localKey] = $targets[$sourceKey];
            $targets[$refKey] = $targets[$sourceKey];

            $dropSql[] = sprintf(
                'ALTER TABLE %s DROP FOREIGN KEY %s',
                $this->quoteIdentifier($fk['table_name']),
                $this->quoteIdentifier($fk['constraint_name']),
            );
            $recreateSql[] = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE %s ON DELETE %s',
                $this->quoteIdentifier($fk['table_name']),
                $this->quoteIdentifier($fk['constraint_name']),
                $this->quoteIdentifier($fk['column_name']),
                $this->quoteIdentifier($fk['referenced_table_name']),
                $this->quoteIdentifier($fk['referenced_column_name']),
                strtoupper((string) $fk['update_rule']),
                strtoupper((string) $fk['delete_rule']),
            );
        }

        $dropSql = array_values(array_unique($dropSql));
        $recreateSql = array_values(array_unique($recreateSql));

        $alterSql = [];
        foreach ($targets as $key => $target) {
            [$table, $column] = explode('.', $key, 2);
            $statement = $this->buildTypeOnlyAlter($table, $column, $target['type'], $target['collation']);
            if ($statement !== null) {
                $alterSql[] = $statement;
            }
        }

        return array_merge($dropSql, $alterSql, $recreateSql);
    }

    /**
     * @return list<array{
     *     constraint_name:string,
     *     table_name:string,
     *     column_name:string,
     *     referenced_table_name:string,
     *     referenced_column_name:string,
     *     update_rule:string,
     *     delete_rule:string
     * }>
     */
    private function fetchSingleColumnForeignKeys(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
              rc.constraint_name,
              kcu.table_name,
              kcu.column_name,
              kcu.referenced_table_name,
              kcu.referenced_column_name,
              rc.update_rule,
              rc.delete_rule
            FROM information_schema.referential_constraints rc
            INNER JOIN information_schema.key_column_usage kcu
              ON rc.constraint_schema = kcu.constraint_schema
             AND rc.table_name = kcu.table_name
             AND rc.constraint_name = kcu.constraint_name
            WHERE rc.constraint_schema = DATABASE()
            GROUP BY
              rc.constraint_name,
              kcu.table_name,
              kcu.column_name,
              kcu.referenced_table_name,
              kcu.referenced_column_name,
              rc.update_rule,
              rc.delete_rule
            HAVING COUNT(*) = 1
            ORDER BY kcu.table_name, rc.constraint_name
            SQL);

        return array_map(
            static fn (array $row): array => [
                'constraint_name' => (string) ($row['constraint_name'] ?? ''),
                'table_name' => (string) ($row['table_name'] ?? ''),
                'column_name' => (string) ($row['column_name'] ?? ''),
                'referenced_table_name' => (string) ($row['referenced_table_name'] ?? ''),
                'referenced_column_name' => (string) ($row['referenced_column_name'] ?? ''),
                'update_rule' => (string) ($row['update_rule'] ?? 'RESTRICT'),
                'delete_rule' => (string) ($row['delete_rule'] ?? 'RESTRICT'),
            ],
            $rows,
        );
    }

    private function buildTypeOnlyAlter(
        string $table,
        string $column,
        string $targetType,
        string $targetCollation,
    ): ?string {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return null;
        }

        $meta = $this->connection->fetchAssociative(<<<'SQL'
            SELECT column_type, is_nullable, collation_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND column_name = :column
            SQL, ['table' => $table, 'column' => $column]);

        if (!is_array($meta)) {
            return null;
        }

        $currentType = strtolower((string) ($meta['column_type'] ?? ''));
        $currentCollation = strtolower((string) ($meta['collation_name'] ?? ''));
        $isNullable = strtoupper((string) ($meta['is_nullable'] ?? 'YES')) === 'YES';
        $nullSql = $isNullable ? 'NULL' : 'NOT NULL';

        if ($currentType === strtolower($targetType) && $currentCollation === strtolower($targetCollation)) {
            return null;
        }

        $collationSql = $targetCollation !== '' ? sprintf('COLLATE %s', $targetCollation) : '';

        return trim(sprintf(
            'ALTER TABLE %s MODIFY %s %s %s %s',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($column),
            strtoupper($targetType),
            $collationSql,
            $nullSql,
        ));
    }

    /**
     * @return list<array{table:string,column:string,type:string,collation:string}>
     */
    private function idTypeRules(): array
    {
        return [
            $this->idRule('almacen', 'almacen_id', 'varchar(255)'),
            $this->idRule('empresa', 'empresa_id', 'varchar(255)'),
            $this->idRule('local', 'local_id', 'varchar(255)'),
            $this->idRule('media', 'id', 'varchar(255)'),
            $this->idRule('operacion', 'operacion_id', 'varchar(255)'),
            $this->idRule('producto', 'producto_id', 'varchar(255)'),
            $this->idRule('proveedor', 'proveedor_id', 'varchar(255)'),
            $this->idRule('tipo_comprobante', 'codigo', 'varchar(2)'),
            $this->idRule('cliente', 'ruc', 'varchar(11)'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rule(
        string $table,
        string $column,
        string $type,
        bool $nullable,
        mixed $default,
        ?string $fillIfNull = null,
    ): array {
        $rule = [
            'table' => $table,
            'column' => $column,
            'type' => $type,
            'nullable' => $nullable,
            'default' => $default,
        ];

        if ($fillIfNull !== null) {
            $rule['fill_if_null'] = $fillIfNull;
        }

        return $rule;
    }

    /**
     * @return array{table:string,column:string,type:string,collation:string}
     */
    private function idRule(string $table, string $column, string $type): array
    {
        return [
            'table' => $table,
            'column' => $column,
            'type' => $type,
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('`%s`', str_replace('`', '``', $identifier));
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table],
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $table, 'column' => $column],
        ) > 0;
    }
}
