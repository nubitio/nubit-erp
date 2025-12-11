<?php

namespace App\Command;

use Exception;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:storage:migrate', description: 'Mueve los archivos de los comprobantes a la nueva ubicación', help: <<<'TXT'
Mueve los archivos de los comprobantes a la nueva ubicación
TXT)]
class StorageMigrateCommand
{
    public function __construct(private readonly FileManager $fileManager, private readonly CompanyContext $companyContext)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $io->title('Migrando archivos de comprobantes');
            $currentPath = dirname(__DIR__, 4) . '/public/' . $_ENV['EFACT_DIR'];
            $newPath = $this->companyContext->getId();
            $files = scandir($currentPath);

            if (!$files) {
                $io->info('No hay archivos para migrar');

                return Command::SUCCESS;
            }

            $ignore = ['.', '..', 'README.md', '.gitignore'];

            $progressBar = $io->createProgressBar(count($files));
            foreach ($files as $file) {
                if (in_array($file, $ignore)) {
                    continue;
                }

                $progressBar->setMessage('Migrando archivo: ' . $file);
                $data = file_get_contents($currentPath . '/' . $file);

                if (!$data) {
                    throw new Exception('Error al leer el archivo: ' . $file);
                }

                $this->fileManager->write($newPath . '/' . $file, $data);
                if ($this->fileManager->exists($newPath . '/' . $file)) {
                    $progressBar->setMessage('Archivo migrado: ' . $file);
                    unlink($currentPath . '/' . $file);
                } else {
                    throw new Exception('Error al migrar el archivo: ' . $file);
                }

                $progressBar->advance();
            }

            $progressBar->finish();

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $io->error('Error: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
