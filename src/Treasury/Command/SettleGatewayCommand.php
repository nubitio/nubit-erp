<?php

declare(strict_types=1);

namespace App\Treasury\Command;

use App\Settings\Capability\CapabilityNotAvailableException;
use App\Treasury\Domain\GatewaySettlement;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Service\SettleGateway;
use DateTimeImmutable;
use DateTimeZone;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'treasury:gateway:settle', description: 'Allocate captured funds to a confirmed PEN gateway payout.')]
final class SettleGatewayCommand extends PerTenantCommand
{
    public function __construct(
        private readonly SettleGateway $settle,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('clearing', InputArgument::REQUIRED, 'Treasury clearing account ID')
            ->addArgument('bank', InputArgument::REQUIRED, 'Treasury bank account ID')
            ->addArgument('reference', InputArgument::REQUIRED, 'Provider payout reference')
            ->addArgument('net-minor', InputArgument::REQUIRED, 'Net bank amount in cents')
            ->addArgument('fee-minor', InputArgument::REQUIRED, 'Fee in cents')
            ->addArgument('paid-at', InputArgument::REQUIRED, 'UTC timestamp YYYY-MM-DDTHH:MM:SSZ')
            ->addOption('fee-account', null, InputOption::VALUE_REQUIRED, 'Expense ledger code for the fee')
            ->addOption(
                'capture',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Treasury movement ID of a capture',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (trim((string) $input->getOption('tenant')) === '') {
            new SymfonyStyle($input, $output)->error('Indica el tenant con --tenant.');

            return Command::INVALID;
        }

        return parent::execute($input, $output);
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $net = filter_var($input->getArgument('net-minor'), FILTER_VALIDATE_INT);
        $fee = filter_var($input->getArgument('fee-minor'), FILTER_VALIDATE_INT);
        $timestamp = (string) $input->getArgument('paid-at');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $timestamp, new DateTimeZone('UTC'));
        if ($net === false || $fee === false || $date === false || $date->format('Y-m-d\TH:i:s\Z') !== $timestamp) {
            $io->error('Usa céntimos enteros y una fecha UTC con precisión de segundos.');

            return Command::INVALID;
        }

        $feeCode = $input->getOption('fee-account');
        $captureIds = array_map(strval(...), array_values((array) $input->getOption('capture')));
        try {
            $id = $this->settle->settle(
                new GatewaySettlement(
                    (string) $input->getArgument('clearing'),
                    (string) $input->getArgument('bank'),
                    (string) $input->getArgument('reference'),
                    $net,
                    $fee,
                    $date,
                    is_string($feeCode) ? $feeCode : null,
                    $captureIds,
                ),
            );
        } catch (TreasuryException|CapabilityNotAvailableException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Liquidación: ' . $id);

        return Command::SUCCESS;
    }
}
