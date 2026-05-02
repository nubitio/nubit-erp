<?php

declare(strict_types=1);

namespace App\Purchasing\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
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
    name: 'app:purchases:v2:baseline',
    description: 'Apply Purchases V2 baseline flags (mode/dual-write/strict).',
)]
final class PurchasesV2BaselineCommand extends PerTenantCommand
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
                'dual_write'
            )
            ->addOption(
                'write-new-schema',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enable writes to Purchases V2 tables in non-legacy modes',
                '1'
            )
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_OPTIONAL,
                'Fail inbound save when Purchases V2 write fails',
                '0'
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

        $writeNewSchema = $this->toBoolString((string) $input->getOption('write-new-schema'));
        $strict = $this->toBoolString((string) $input->getOption('strict'));

        $changes = [
            [Constants::PURCHASES_V2_MODE, $mode, 'string'],
            [Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, $writeNewSchema, 'bool'],
            [Constants::PURCHASES_V2_DUAL_WRITE_STRICT, $strict, 'bool'],
        ];

        $io->table(['Config key', 'Value', 'Type'], $changes);

        if ((bool) $input->getOption('dry-run')) {
            $io->note('Dry-run enabled: no changes applied.');

            return Command::SUCCESS;
        }

        foreach ($changes as [$name, $value, $type]) {
            $this->upsertConfig((string) $name, (string) $value, (string) $type);
        }

        $io->success('Purchases V2 baseline applied successfully.');

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
