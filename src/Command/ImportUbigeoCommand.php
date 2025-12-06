<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
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
class ImportUbigeoCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al CSV oficial de ubigeo')
            ->addOption('delimiter', null, InputOption::VALUE_OPTIONAL, 'Delimitador del CSV', ',')
            ->addOption('skip-header', null, InputOption::VALUE_NONE, 'Indica si el CSV incluye cabecera');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('file');
        $delimiter = (string) $input->getOption('delimiter');
        $skipHeader = (bool) $input->getOption('skip-header');

        if (!is_readable($filePath)) {
            $io->error(sprintf('No se puede leer el archivo: %s', $filePath));

            return Command::FAILURE;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            $io->error('No se pudo abrir el archivo CSV.');

            return Command::FAILURE;
        }

        if ($skipHeader) {
            fgetcsv($handle, 0, $delimiter);
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO ubigeo (codigo, departamento, provincia, distrito)
             VALUES (:code, :department, :province, :district)
             ON DUPLICATE KEY UPDATE
                departamento = VALUES(departamento),
                provincia = VALUES(provincia),
                distrito = VALUES(distrito)'
        );

        $this->connection->beginTransaction();
        try {
            $count = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                // Ajusta los índices según las columnas reales del CSV:
                // 0 => codigo, 1 => departamento, 2 => provincia, 3 => distrito
                if (count($row) < 4) {
                    continue;
                }

                $stmt->bindValue('code', trim((string) $row[0]));
                $stmt->bindValue('department', trim((string) $row[1]));
                $stmt->bindValue('province', trim((string) $row[2]));
                $stmt->bindValue('district', trim((string) $row[3]));
                $stmt->executeStatement();
                $count++;
            }

            $this->connection->commit();
            fclose($handle);

            $io->success(sprintf('Importación completada. Filas procesadas: %d', $count));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            fclose($handle);
            $io->error('Error al importar: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
