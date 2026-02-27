<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:sales:v2:inventory',
    description: 'Inventory Sales V1/V2 dependencies and migration readiness gaps.',
)]
final class SalesV2InventoryCommand extends TenantAwareCommand
{
    private bool $sourceScanRendered = false;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->sourceScanRendered) {
            $this->renderSourceInventory($io);
            $this->sourceScanRendered = true;
        }

        $this->renderTenantDbInventory($io);

        return Command::SUCCESS;
    }

    private function renderSourceInventory(SymfonyStyle $io): void
    {
        $io->section('Source Inventory (single scan)');

        $root = $this->projectDir . '/src';
        if (!is_dir($root)) {
            $io->warning('Source directory not found: ' . $root);

            return;
        }

        $legacyPatterns = [
            'movimiento_detalle',
            'from movimiento',
            'join movimiento',
            'update movimiento',
            'insert into movimiento',
        ];

        $v2Patterns = [
            'from sales',
            'join sales',
            'insert into sales',
            'sale_items',
            'cash_sessions',
            'stock_movements',
        ];

        $legacyMatches = [];
        $v2Matches = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $path = $fileInfo->getPathname();
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($path);
            if (!is_string($content) || $content === '') {
                continue;
            }

            $lc = strtolower($content);
            $legacyHit = [];
            $v2Hit = [];

            foreach ($legacyPatterns as $pattern) {
                if (str_contains($lc, $pattern)) {
                    $legacyHit[] = $pattern;
                }
            }

            foreach ($v2Patterns as $pattern) {
                if (str_contains($lc, $pattern)) {
                    $v2Hit[] = $pattern;
                }
            }

            if ($legacyHit !== []) {
                $legacyMatches[$path] = implode(', ', $legacyHit);
            }

            if ($v2Hit !== []) {
                $v2Matches[$path] = implode(', ', $v2Hit);
            }
        }

        $legacyRows = [];
        foreach ($legacyMatches as $path => $patterns) {
            $legacyRows[] = [str_replace($this->projectDir . '/', '', $path), $patterns];
        }

        $v2Rows = [];
        foreach ($v2Matches as $path => $patterns) {
            $v2Rows[] = [str_replace($this->projectDir . '/', '', $path), $patterns];
        }

        usort($legacyRows, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
        usort($v2Rows, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $io->text(sprintf('Legacy-touch files: %d', count($legacyRows)));
        if ($legacyRows !== []) {
            $io->table(['File', 'Legacy markers'], array_slice($legacyRows, 0, 40));
            if (count($legacyRows) > 40) {
                $io->note(sprintf('Showing first 40/%d legacy-touch files.', count($legacyRows)));
            }
        }

        $io->text(sprintf('V2-touch files: %d', count($v2Rows)));
        if ($v2Rows !== []) {
            $io->table(['File', 'V2 markers'], array_slice($v2Rows, 0, 40));
            if (count($v2Rows) > 40) {
                $io->note(sprintf('Showing first 40/%d V2-touch files.', count($v2Rows)));
            }
        }
    }

    private function renderTenantDbInventory(SymfonyStyle $io): void
    {
        $io->section('Tenant DB Inventory');

        try {
            $legacyOperationalSales = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM movimiento WHERE tipo_id = 2'
            );

            $v2Sales = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales');

            $legacyNotMirrored = (int) $this->connection->fetchOne(
                <<<'SQL'
SELECT COUNT(*)
FROM movimiento m
WHERE m.tipo_id = 2
  AND NOT EXISTS (
      SELECT 1
      FROM sales s
      WHERE s.legacy_movimiento_id = m.movimiento_id
  )
SQL
            );

            $v2NativeNoLegacyLink = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM sales WHERE legacy_movimiento_id IS NULL'
            );

            $cashSessionCoverageGap = (int) $this->connection->fetchOne(
                <<<'SQL'
SELECT COUNT(*)
FROM sales
WHERE cash_session_id IS NULL OR cash_session_id <= 0
SQL
            );

            $io->table(
                ['Metric', 'Value'],
                [
                    ['legacy_operational_sales (movimiento tipo_id=2)', (string) $legacyOperationalSales],
                    ['v2_sales (sales)', (string) $v2Sales],
                    ['legacy_not_mirrored_to_v2', (string) $legacyNotMirrored],
                    ['v2_native_without_legacy_link', (string) $v2NativeNoLegacyLink],
                    ['v2_sales_without_cash_session', (string) $cashSessionCoverageGap],
                ]
            );

            if ($legacyNotMirrored > 0) {
                $io->warning('There are legacy operational sales not mirrored to Sales V2.');
            }
        } catch (Exception $exception) {
            $io->warning('Skipping tenant DB inventory: ' . $exception->getMessage());
        }
    }
}
