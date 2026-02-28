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
    name: 'app:schema:audit:tenants',
    description: 'Audit DB schema drift across tenants (tables, columns, indexes and constraints).',
)]
final class TenantSchemaAuditCommand extends TenantAwareCommand
{
    private ?string $baselineTenant = null;
    private bool $normalizeFkActions = true;

    /** @var array<string, array<string, array<int, string>>>|null */
    private ?array $baselineSnapshot = null;

    /** @var array<string, array<string, array<int, string>>> */
    private array $deferredSnapshots = [];

    /** @var array<string, array<string, array<int, string>>> */
    private array $driftByTenant = [];

    /** @var array<string, array<string, int|string>> */
    private array $driftSummaryByTenant = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'baseline-tenant',
            null,
            InputOption::VALUE_OPTIONAL,
            'Tenant name to use as canonical baseline. Defaults to first processed tenant.'
        );
        $this->addOption(
            'strict',
            null,
            InputOption::VALUE_NONE,
            'Return failure when schema drift is detected.'
        );
        $this->addOption(
            'strict-fk-actions',
            null,
            InputOption::VALUE_NONE,
            'Do not normalize FK actions (treat NO ACTION and RESTRICT as different).'
        );
        $this->addOption(
            'output',
            null,
            InputOption::VALUE_OPTIONAL,
            'Write audit report as JSON.',
            'var/schema-audit-tenants.json'
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('parallel') !== false) {
            $io->error('Parallel mode is not supported for cross-tenant schema audit. Run without --parallel.');
            return Command::FAILURE;
        }

        $this->baselineTenant = null;
        $this->baselineSnapshot = null;
        $this->deferredSnapshots = [];
        $this->driftByTenant = [];
        $this->driftSummaryByTenant = [];
        $this->normalizeFkActions = !(bool) $input->getOption('strict-fk-actions');

        $code = parent::execute($input, $output);

        $requestedBaseline = trim((string) ($input->getOption('baseline-tenant') ?? ''));
        if ($requestedBaseline !== '' && $this->baselineTenant === null) {
            $io->error(sprintf('Baseline tenant "%s" was not found in processing scope.', $requestedBaseline));
            return Command::FAILURE;
        }

        $outputPath = trim((string) ($input->getOption('output') ?? ''));
        if ($outputPath !== '') {
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            file_put_contents(
                $outputPath,
                json_encode([
                    'baseline_tenant' => $this->baselineTenant,
                    'drift' => $this->driftByTenant,
                    'summary' => $this->driftSummaryByTenant,
                    'normalize_fk_actions' => $this->normalizeFkActions,
                    'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $io->note(sprintf('Schema audit report written to %s', $outputPath));
        }

        return $code;
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $strict = (bool) $input->getOption('strict');
        $requestedBaseline = trim((string) ($input->getOption('baseline-tenant') ?? ''));
        $tenantName = $this->currentTenantName ?? 'unknown';

        $snapshot = $this->buildSchemaSnapshot();

        if ($this->baselineSnapshot === null) {
            if ($requestedBaseline !== '' && $tenantName !== $requestedBaseline) {
                $this->deferredSnapshots[$tenantName] = $snapshot;
                $io->note(sprintf('Deferred comparison for %s (waiting baseline tenant %s).', $tenantName, $requestedBaseline));
                return Command::SUCCESS;
            }

            $this->baselineSnapshot = $snapshot;
            $this->baselineTenant = $tenantName;
            $io->note(sprintf('Baseline schema set to tenant %s.', $tenantName));

            foreach ($this->deferredSnapshots as $deferredTenant => $deferredSnapshot) {
                $drift = $this->diffSnapshot($this->baselineSnapshot, $deferredSnapshot);
                if ($this->hasDrift($drift)) {
                    $this->driftByTenant[$deferredTenant] = $drift;
                    $this->driftSummaryByTenant[$deferredTenant] = $this->buildDriftSummary($drift);
                }
            }
            $this->deferredSnapshots = [];

            return Command::SUCCESS;
        }

        $drift = $this->diffSnapshot($this->baselineSnapshot, $snapshot);
        if (!$this->hasDrift($drift)) {
            $io->success(sprintf('Schema aligned with baseline (%s).', $this->baselineTenant));
            return Command::SUCCESS;
        }

        $this->driftByTenant[$tenantName] = $drift;
        $summary = $this->buildDriftSummary($drift);
        $this->driftSummaryByTenant[$tenantName] = $summary;
        $io->warning(sprintf('Schema drift detected against baseline tenant %s.', $this->baselineTenant));
        $io->table(
            ['Category', 'Missing', 'Extra'],
            [
                ['Tables', (string) count($drift['tables_missing']), (string) count($drift['tables_extra'])],
                ['Columns', (string) count($drift['columns_missing']), (string) count($drift['columns_extra'])],
                ['Indexes', (string) count($drift['indexes_missing']), (string) count($drift['indexes_extra'])],
                ['Constraints', (string) count($drift['constraints_missing']), (string) count($drift['constraints_extra'])],
            ]
        );
        $io->note(sprintf(
            'Critical drift count: %d (columns=%d, constraints=%d, tables=%d, indexes=%d).',
            $summary['critical_total'],
            $summary['columns_total'],
            $summary['constraints_total'],
            $summary['tables_total'],
            $summary['indexes_total']
        ));

        return $strict ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildSchemaSnapshot(): array
    {
        $tables = $this->connection->fetchFirstColumn(<<<'SQL'
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_type = 'BASE TABLE'
ORDER BY table_name
SQL);

        $columns = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT table_name, column_name, column_type, is_nullable, COALESCE(column_default, 'NULL') AS column_default,
       COALESCE(extra, '') AS extra, COALESCE(collation_name, '') AS collation_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
ORDER BY table_name, ordinal_position
SQL);

        $indexes = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT table_name, non_unique, index_type, index_name,
       GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') AS column_list
FROM information_schema.statistics
WHERE table_schema = DATABASE()
GROUP BY table_name, non_unique, index_type, index_name
ORDER BY table_name, index_name
SQL);

        $constraints = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT
  tc.table_name,
  tc.constraint_type,
  tc.constraint_name,
  GROUP_CONCAT(kcu.column_name ORDER BY kcu.ordinal_position SEPARATOR ',') AS column_list,
  COALESCE(kcu.referenced_table_name, '') AS referenced_table_name,
  GROUP_CONCAT(kcu.referenced_column_name ORDER BY kcu.ordinal_position SEPARATOR ',') AS referenced_column_list,
  COALESCE(rc.update_rule, '') AS update_rule,
  COALESCE(rc.delete_rule, '') AS delete_rule,
  COALESCE(cc.check_clause, '') AS check_clause
FROM information_schema.table_constraints tc
LEFT JOIN information_schema.key_column_usage kcu
  ON tc.constraint_schema = kcu.constraint_schema
 AND tc.table_name = kcu.table_name
 AND tc.constraint_name = kcu.constraint_name
LEFT JOIN information_schema.referential_constraints rc
  ON tc.constraint_schema = rc.constraint_schema
 AND tc.table_name = rc.table_name
 AND tc.constraint_name = rc.constraint_name
LEFT JOIN information_schema.check_constraints cc
  ON tc.constraint_schema = cc.constraint_schema
 AND tc.constraint_name = cc.constraint_name
WHERE tc.constraint_schema = DATABASE()
GROUP BY
  tc.table_name,
  tc.constraint_type,
  tc.constraint_name,
  kcu.referenced_table_name,
  rc.update_rule,
  rc.delete_rule,
  cc.check_clause
ORDER BY tc.table_name, tc.constraint_type, tc.constraint_name
SQL);

        $tableSignatures = array_values(array_filter(array_map(
            static fn (mixed $table): string => strtolower((string) $table),
            $tables
        ), static fn (string $table): bool => $table !== 'doctrine_migration_versions'));

        $columnSignatures = [];
        foreach ($columns as $column) {
            $table = strtolower((string) ($column['table_name'] ?? ''));
            if ($table === '' || $table === 'doctrine_migration_versions') {
                continue;
            }

            $columnSignatures[] = sprintf(
                '%s.%s|%s|%s|%s|%s|%s',
                $table,
                strtolower((string) ($column['column_name'] ?? '')),
                strtolower((string) ($column['column_type'] ?? '')),
                strtolower((string) ($column['is_nullable'] ?? '')),
                strtolower(trim((string) ($column['column_default'] ?? 'null'))),
                strtolower((string) ($column['extra'] ?? '')),
                strtolower((string) ($column['collation_name'] ?? ''))
            );
        }

        $indexSignatures = [];
        foreach ($indexes as $index) {
            $table = strtolower((string) ($index['table_name'] ?? ''));
            if ($table === '' || $table === 'doctrine_migration_versions') {
                continue;
            }

            $indexSignatures[] = sprintf(
                '%s|%s|%s|%s',
                $table,
                (string) ($index['non_unique'] ?? '1'),
                strtolower((string) ($index['index_type'] ?? 'btree')),
                strtolower((string) ($index['column_list'] ?? ''))
            );
        }

        $constraintSignatures = [];
        foreach ($constraints as $constraint) {
            $table = strtolower((string) ($constraint['table_name'] ?? ''));
            if ($table === '' || $table === 'doctrine_migration_versions') {
                continue;
            }

            $type = strtoupper((string) ($constraint['constraint_type'] ?? ''));
            $columnsList = strtolower((string) ($constraint['column_list'] ?? ''));
            $refTable = strtolower((string) ($constraint['referenced_table_name'] ?? ''));
            $refColumns = strtolower((string) ($constraint['referenced_column_list'] ?? ''));
            $updateRule = strtoupper((string) ($constraint['update_rule'] ?? ''));
            $deleteRule = strtoupper((string) ($constraint['delete_rule'] ?? ''));
            $check = preg_replace('/\s+/', ' ', strtolower(trim((string) ($constraint['check_clause'] ?? ''))));

            if ($type === 'FOREIGN KEY') {
                if ($this->normalizeFkActions) {
                    $updateRule = $this->normalizeFkRule($updateRule);
                    $deleteRule = $this->normalizeFkRule($deleteRule);
                }

                $constraintSignatures[] = sprintf(
                    'FK|%s|%s|%s|%s|%s|%s',
                    $table,
                    $columnsList,
                    $refTable,
                    $refColumns,
                    $updateRule,
                    $deleteRule
                );

                continue;
            }

            if ($type === 'CHECK') {
                $constraintSignatures[] = sprintf('CHECK|%s|%s', $table, $check);
                continue;
            }

            $constraintSignatures[] = sprintf('%s|%s|%s', $type, $table, $columnsList);
        }

        sort($tableSignatures);
        sort($columnSignatures);
        sort($indexSignatures);
        sort($constraintSignatures);

        return [
            'tables' => array_values(array_unique($tableSignatures)),
            'columns' => array_values(array_unique($columnSignatures)),
            'indexes' => array_values(array_unique($indexSignatures)),
            'constraints' => array_values(array_unique($constraintSignatures)),
        ];
    }

    /**
     * @param array<string, array<int, string>> $baseline
     * @param array<string, array<int, string>> $current
     *
     * @return array<string, array<int, string>>
     */
    private function diffSnapshot(array $baseline, array $current): array
    {
        return [
            'tables_missing' => array_values(array_diff($baseline['tables'], $current['tables'])),
            'tables_extra' => array_values(array_diff($current['tables'], $baseline['tables'])),
            'columns_missing' => array_values(array_diff($baseline['columns'], $current['columns'])),
            'columns_extra' => array_values(array_diff($current['columns'], $baseline['columns'])),
            'indexes_missing' => array_values(array_diff($baseline['indexes'], $current['indexes'])),
            'indexes_extra' => array_values(array_diff($current['indexes'], $baseline['indexes'])),
            'constraints_missing' => array_values(array_diff($baseline['constraints'], $current['constraints'])),
            'constraints_extra' => array_values(array_diff($current['constraints'], $baseline['constraints'])),
        ];
    }

    /**
     * @param array<string, array<int, string>> $drift
     *
     * @return array<string, int|string>
     */
    private function buildDriftSummary(array $drift): array
    {
        $tablesTotal = count($drift['tables_missing']) + count($drift['tables_extra']);
        $columnsTotal = count($drift['columns_missing']) + count($drift['columns_extra']);
        $indexesTotal = count($drift['indexes_missing']) + count($drift['indexes_extra']);
        $constraintsTotal = count($drift['constraints_missing']) + count($drift['constraints_extra']);

        return [
            'tables_total' => $tablesTotal,
            'columns_total' => $columnsTotal,
            'indexes_total' => $indexesTotal,
            'constraints_total' => $constraintsTotal,
            'critical_total' => $tablesTotal + $columnsTotal + $indexesTotal + $constraintsTotal,
            'baseline_tenant' => $this->baselineTenant ?? '',
        ];
    }

    private function normalizeFkRule(string $rule): string
    {
        $normalized = strtoupper(trim($rule));

        if ($normalized === 'NO ACTION') {
            return 'RESTRICT';
        }

        return $normalized;
    }

    /**
     * @param array<string, array<int, string>> $drift
     */
    private function hasDrift(array $drift): bool
    {
        foreach ($drift as $rows) {
            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }
}
