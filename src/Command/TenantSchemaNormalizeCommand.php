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
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $skipMenuId = (bool) $input->getOption('skip-menu-id');
        $skipUnidadCode = (bool) $input->getOption('skip-unidad-code');

        $statements = [];

        if (!$skipMenuId) {
            $statements = array_merge($statements, $this->buildMenuIdNormalization());
        }
        if (!$skipUnidadCode) {
            $statements = array_merge($statements, $this->buildUnidadCodeNormalization());
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
SELECT data_type, is_nullable, extra
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

        if (!in_array($dataType, ['int', 'integer', 'bigint'], true)) {
            return [];
        }

        if (str_contains($extra, 'auto_increment') && !$isNullable) {
            return [];
        }

        $typeSql = in_array($dataType, ['bigint'], true) ? 'BIGINT' : 'INT';

        return [sprintf('ALTER TABLE menu MODIFY id %s NOT NULL AUTO_INCREMENT', $typeSql)];
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
