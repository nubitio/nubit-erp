<?php

namespace App\Core\Infrastructure\Command;

use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:storage:migrate',
    description: 'Mueve los archivos de los comprobantes a la nueva ubicación',
)]
class StorageMigrateCommand extends Command
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Mueve los archivos de los comprobantes a la nueva ubicación');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Migrando archivos de comprobantes');
            $currentPath = dirname(__DIR__, 4) . '/public/' . $_ENV['EFACT_DIR'];
            $newPath = $this->settingsService->get(Constants::COMPANY_ID);
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
                    throw new \Exception('Error al leer el archivo: ' . $file);
                }

                $this->fileManager->write($newPath . '/' . $file, $data);
                if ($this->fileManager->exists($newPath . '/' . $file)) {
                    $progressBar->setMessage('Archivo migrado: ' . $file);
                    unlink($currentPath . '/' . $file);
                } else {
                    throw new \Exception('Error al migrar el archivo: ' . $file);
                }
                $progressBar->advance();
            }
            $progressBar->finish();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
