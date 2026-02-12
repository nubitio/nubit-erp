<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use App\Core\Util\Constants;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:migrate-tenant-prefix',
    description: 'Migrate media files from images/{path} to images/{tenantName}/{path}',
)]
final class MigrateMediaToTenantPrefixCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FilesystemOperator $uploadsFilesystem,
        TenantManager $tenantManager,
        TenantConnectionSwitcher $tenantConnectionSwitcher,
        LoggerInterface $logger,
    ) {
        parent::__construct($tenantManager, $tenantConnectionSwitcher, $logger);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            name: 'delete-originals',
            mode: InputOption::VALUE_NONE,
            description: 'Delete original files after copying to the tenant-prefixed path',
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = $this->currentTenantName;

        if ($tenantName === null) {
            $io->error('Tenant name not available');

            return Command::FAILURE;
        }

        $deleteOriginals = (bool) $input->getOption('delete-originals');

        $paths = $this->connection->fetchFirstColumn('SELECT path FROM media');

        if ($paths === []) {
            $io->info('No media files found for this tenant');

            return Command::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($paths as $path) {
            $sourcePath = Constants::IMAGE_UPLOAD_DIR . $path;
            $targetPath = Constants::IMAGE_UPLOAD_DIR . $tenantName . '/' . $path;

            try {
                if (!$this->uploadsFilesystem->fileExists($sourcePath)) {
                    $skipped++;
                    $io->writeln(
                        sprintf('  <comment>Skipped</comment> %s (not found)', $sourcePath),
                        OutputInterface::VERBOSITY_VERBOSE,
                    );

                    continue;
                }

                if ($this->uploadsFilesystem->fileExists($targetPath)) {
                    $skipped++;
                    $io->writeln(
                        sprintf('  <comment>Skipped</comment> %s (already exists at target)', $targetPath),
                        OutputInterface::VERBOSITY_VERBOSE,
                    );

                    continue;
                }

                $this->uploadsFilesystem->copy($sourcePath, $targetPath);

                if ($deleteOriginals) {
                    $this->uploadsFilesystem->delete($sourcePath);
                }

                $migrated++;
                $io->writeln(
                    sprintf('  <info>Migrated</info> %s → %s', $sourcePath, $targetPath),
                    OutputInterface::VERBOSITY_VERBOSE,
                );
            } catch (FilesystemException $e) {
                $errors++;
                $this->logger->error('Failed to migrate media file', [
                    'tenant' => $tenantName,
                    'source' => $sourcePath,
                    'target' => $targetPath,
                    'exception' => $e,
                ]);
                $io->writeln(sprintf('  <error>Error</error> %s: %s', $sourcePath, $e->getMessage()));
            }
        }

        $io->writeln(sprintf(
            'Migrated: %d, Skipped: %d, Errors: %d',
            $migrated,
            $skipped,
            $errors,
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
