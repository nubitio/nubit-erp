<?php

namespace App\Invoice\Application;

use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\XlsExporter;
use League\Flysystem\FilesystemException;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:check',
    description: 'Check invoice',
)]
class CheckInvoiceCommand extends Command
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly XlsExporter $xlsExporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Verifica comprobantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $dirList = $this->fileManager->listContents('.');
            $dirList = $dirList->toArray();

            /** @var array<int, array<string, string>> $dirList */
            $dirs = array_filter($dirList, fn ($item) => 'dir' === $item['type'] && 'images' !== $item['path']);
            /** @var array<string, string> $dir */
            foreach ($dirs as $dir) {
                $filesList = $this->fileManager->listContents($dir['path']);
                $filesList = $filesList->toArray();
                /** @var array<int, array<string, string>> $filesList */
                $files = array_filter($filesList, fn ($item) => 'file' === $item['type'] && str_contains($item['path'], '.xml'));
                $files = array_filter($files, fn ($item) => !str_contains(explode('/', $item['path'])[1], $dir['path']));
                $filesArray = array_map(function ($item) {
                    $path = explode('/', $item['path'])[1];
                    $parts = explode('-', $path);

                    return [
                        'path' => $path,
                        'tenant' => $parts[0],
                        'type' => $parts[1],
                        'serie' => $parts[2],
                        'number' => explode('.', $parts[3])[0],
                        'last_modified' => date('Y-m-d H:i:s', intval($item['lastModified'])),
                    ];
                }, $files);
                $io->title('Tenant: '.$dir['path']);
                $io->table(['path', 'tenant', 'type', 'serie', 'number', 'last_modified'], $filesArray);
                if (count($filesArray) > 0) {
                    $this->xlsExporter->save($filesArray, strval($dir['path']));
                }
            }

            $io->success('Done!');

            return Command::SUCCESS;
        } catch (FilesystemException|\Doctrine\DBAL\Exception|Exception $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
