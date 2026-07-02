<?php

namespace App\Settings\Command;

use App\Settings\Service\UbigeoImportService;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-ubigeo',
    description: 'Importa los códigos de ubigeo (INEI) desde un CSV y los guarda en la tabla ubigeo.',
)]
class ImportUbigeoCommand extends PerTenantCommand
{
    public function __construct(
        private readonly UbigeoImportService $ubigeoImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al CSV oficial de ubigeo')
            ->addOption('delimiter', null, InputOption::VALUE_OPTIONAL, 'Delimitador del CSV', ',')
            ->addOption('skip-header', null, InputOption::VALUE_NONE, 'Indica si el CSV incluye cabecera')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Tamaño del lote (filas por INSERT)', 200);

        parent::configure();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('file');
        $delimiter = (string) $input->getOption('delimiter');
        $skipHeader = (bool) $input->getOption('skip-header');
        $batchSize = (int) $input->getOption('batch-size');

        if (!is_readable($filePath)) {
            $io->error(sprintf('No se puede leer el archivo: %s', $filePath));

            return Command::FAILURE;
        }

        try {
            $io->progressStart();
            $count = $this->ubigeoImportService->import(
                filePath: $filePath,
                delimiter: $delimiter,
                skipHeader: $skipHeader,
                batchSize: $batchSize,
                progressCallback: static function (int $processed) use ($io): void {
                    $io->progressAdvance($processed);
                },
            );

            $io->progressFinish();

            $io->success(sprintf('Importación completada. Filas procesadas: %d', $count));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->progressFinish();
            $io->error('Error al importar: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
