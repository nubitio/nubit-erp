<?php

namespace App\Reporting\Command;

use Nubit\Platform\Symfony\Console\TenantAwareCommand;
use App\Reporting\Service\TimeDimensionSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fill-time-dimension',
    description: 'Rellena la tabla time_dimension (date dimension) entre dos fechas.',
)]
class FillTimeDimensionCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly TimeDimensionSeeder $timeDimensionSeeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start', InputArgument::OPTIONAL, 'Fecha de inicio (Y-m-d)', '2010-01-01')
            ->addArgument('end', InputArgument::OPTIONAL, 'Fecha fin (exclusiva, Y-m-d)', null)
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Locale para nombres de día/mes', 'es_PE')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Tamaño del lote (filas por INSERT)', 500);

        parent::configure();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startInput = (string)$input->getArgument('start');
        $endInput = $input->getArgument('end');
        $locale = (string)$input->getOption('locale');
        $batchSize = (int)$input->getOption('batch-size');

        $startDate = new \DateTimeImmutable($startInput);
        $endDate = $endInput ? new \DateTimeImmutable((string)$endInput) :
            (new \DateTimeImmutable())->modify('+1 day');

        if ($startDate >= $endDate) {
            $io->error('La fecha de inicio debe ser menor a la fecha fin.');

            return Command::FAILURE;
        }

        try {
            $io->progressStart();
            $count = $this->timeDimensionSeeder->seed(
                startDate: $startDate,
                endDate: $endDate,
                batchSize: $batchSize,
                locale: $locale,
                progressCallback: static function (int $processed) use ($io): void {
                    $io->progressAdvance($processed);
                },
            );

            $io->progressFinish();
            $io->success(
                sprintf(
                    'Dimensión de fechas completada. Filas procesadas: %d',
                    $count
                )
            );

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->progressFinish();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
