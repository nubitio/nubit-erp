<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Util\Constants;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sales:v2:sunset-legacy',
    description: 'Apply Phase 6 sunset flags for legacy write-paths in Sales V2.',
)]
final class SalesV2SunsetLegacyCommand extends TenantAwareCommand
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

        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only print intended config changes');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $changes = [
            [Constants::SALES_V2_MODE, 'v2_authoritative', 'string'],
            [Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true', 'bool'],
            [Constants::SALES_V2_DUAL_WRITE_STRICT, 'true', 'bool'],
        ];

        $io->table(['Config key', 'Value', 'Type'], $changes);

        if ((bool) $input->getOption('dry-run')) {
            $io->note('Dry-run enabled: no changes applied.');

            return Command::SUCCESS;
        }

        foreach ($changes as [$name, $value, $type]) {
            $this->upsertConfig((string) $name, (string) $value, (string) $type);
        }

        $io->success('Phase 6 sunset flags applied successfully.');

        return Command::SUCCESS;
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
