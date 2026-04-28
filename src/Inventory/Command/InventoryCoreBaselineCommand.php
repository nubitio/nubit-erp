<?php

declare(strict_types=1);

namespace App\Inventory\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Efact\Config\EfactConstants as Constants;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:core:baseline',
    description: 'Apply Inventory Core baseline flags (mode / dual-write / read-from-stock-items).',
)]
final class InventoryCoreBaselineCommand extends TenantAwareCommand
{
    private const array ALLOWED_MODES = ['legacy', 'dual_write', 'v2_authoritative'];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_OPTIONAL,
                'legacy | dual_write | v2_authoritative',
                'v2_authoritative'
            )
            ->addOption(
                'dual-write',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enable ledger dual-write for inventory movements',
                '1'
            )
            ->addOption(
                'read-stock-items',
                null,
                InputOption::VALUE_OPTIONAL,
                'Read stock balances from stock_items table instead of legacy product_stock',
                '1'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only print intended config changes');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $mode = strtolower(trim((string) $input->getOption('mode')));
        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            $io->error(sprintf('Invalid --mode "%s". Allowed: %s', $mode, implode(', ', self::ALLOWED_MODES)));

            return Command::FAILURE;
        }

        $dualWrite = $this->toBoolString((string) $input->getOption('dual-write'));
        $readStockItems = $this->toBoolString((string) $input->getOption('read-stock-items'));

        $changes = [
            [Constants::INVENTORY_CORE_MODE, $mode, 'string'],
            [Constants::INVENTORY_CORE_DUAL_WRITE, $dualWrite, 'bool'],
            [Constants::INVENTORY_CORE_READ_FROM_STOCK_ITEMS, $readStockItems, 'bool'],
        ];

        $io->table(['Config key', 'Value', 'Type'], $changes);

        if ((bool) $input->getOption('dry-run')) {
            $io->note('Dry-run enabled: no changes applied.');

            return Command::SUCCESS;
        }

        foreach ($changes as [$name, $value, $type]) {
            $this->upsertConfig((string) $name, (string) $value, (string) $type);
        }

        $io->success('Inventory Core baseline applied successfully.');

        return Command::SUCCESS;
    }

    private function toBoolString(string $raw): string
    {
        $value = strtolower(trim($raw));

        return in_array($value, ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false';
    }

    private function upsertConfig(string $name, string $value, string $type): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO config (name, value, type)
VALUES (:name, :value, :type)
ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type)
SQL,
            [
                'name' => $name,
                'value' => $value,
                'type' => $type,
            ]
        );
    }
}
