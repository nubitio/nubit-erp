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
    name: 'app:sales:v2:set-cutover',
    description: 'Set Sales V2 cutover date and mode in config table.',
)]
final class SalesV2SetCutoverCommand extends TenantAwareCommand
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
        $this->addOption(
            'date',
            null,
            InputOption::VALUE_REQUIRED,
            'Cutover date (Y-m-d).'
        );
        $this->addOption(
            'mode',
            null,
            InputOption::VALUE_OPTIONAL,
            'Mode: legacy | dual_write | v2_authoritative',
            'dual_write'
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateRaw = trim((string) $input->getOption('date'));
        $mode = trim((string) $input->getOption('mode'));

        if ($dateRaw === '') {
            $io->error('Option --date is required (Y-m-d).');
            return Command::FAILURE;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        if (!$date instanceof \DateTimeImmutable) {
            $io->error(sprintf('Invalid --date format: %s (expected Y-m-d).', $dateRaw));
            return Command::FAILURE;
        }

        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            $io->error(sprintf('Invalid --mode: %s. Allowed: %s', $mode, implode(', ', self::ALLOWED_MODES)));
            return Command::FAILURE;
        }

        $this->upsertConfig(Constants::SALES_V2_CUTOVER_DATE, $date->format('Y-m-d'), 'string');
        $this->upsertConfig(Constants::SALES_V2_MODE, $mode, 'string');

        $io->success(sprintf(
            'Sales V2 cutover updated: date=%s, mode=%s',
            $date->format('Y-m-d'),
            $mode
        ));

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
