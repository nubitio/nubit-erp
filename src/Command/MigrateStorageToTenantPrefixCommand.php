<?php

declare(strict_types=1);

namespace App\Command;

use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use App\ControlPlane\Tenant\Service\TenantManager;
use App\Efact\Config\EfactConstants as Constants;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ConnectionRegistry;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:storage:migrate-tenant',
    description: 'Migrate S3 files from flat layout to tenant-prefixed layout',
)]
final class MigrateStorageToTenantPrefixCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly FilesystemOperator $defaultFilesystem,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'delete-originals',
                mode: InputOption::VALUE_NONE,
                description: 'Delete original files after successful copy',
            )
            ->addOption(
                name: 'tenant',
                shortcut: 't',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Migrate only a specific tenant',
            )
            ->addOption(
                name: 'type',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Migrate only a specific type (backups, images, invoices)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $deleteOriginals = (bool) $input->getOption('delete-originals');
        $tenantFilter = $input->getOption('tenant');
        $typeFilter = $input->getOption('type');

        $validTypes = [Constants::BACKUP_DIR, Constants::IMAGE_DIR, Constants::INVOICE_DIR];
        if ($typeFilter !== null && !\in_array($typeFilter, $validTypes, true)) {
            $io->error('Invalid type. Valid types: ' . implode(', ', $validTypes));

            return Command::FAILURE;
        }

        $tenants = $this->tenantManager->getTenants();
        if ($tenants === []) {
            $io->warning('No tenants found');

            return Command::SUCCESS;
        }

        if ($tenantFilter !== null) {
            $tenants = array_filter($tenants, static fn (array $t): bool => $t['name'] === $tenantFilter);
            if ($tenants === []) {
                $io->error(sprintf('Tenant "%s" not found', $tenantFilter));

                return Command::FAILURE;
            }
        }

        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($tenants as $tenant) {
            $tenantName = (string) $tenant['name'];
            $tenantId = (int) $tenant['id'];
            $io->title('Tenant: ' . $tenantName);

            if ($typeFilter === null || $typeFilter === Constants::BACKUP_DIR) {
                [$m, $s, $e] = $this->migrateBackups($io, $tenantName, $tenantId, $deleteOriginals);
                $totalMigrated += $m;
                $totalSkipped += $s;
                $totalErrors += $e;
            }

            if ($typeFilter === null || $typeFilter === Constants::IMAGE_DIR) {
                [$m, $s, $e] = $this->migrateImages($io, $tenantName, $deleteOriginals);
                $totalMigrated += $m;
                $totalSkipped += $s;
                $totalErrors += $e;
            }

            if ($typeFilter === null || $typeFilter === Constants::INVOICE_DIR) {
                [$m, $s, $e] = $this->migrateInvoices($io, $tenantName, $deleteOriginals);
                $totalMigrated += $m;
                $totalSkipped += $s;
                $totalErrors += $e;
            }
        }

        $io->newLine();
        $io->writeln(sprintf(
            '<info>Total</info> — Migrated: %d, Skipped: %d, Errors: %d',
            $totalMigrated,
            $totalSkipped,
            $totalErrors,
        ));

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array{int, int, int} [migrated, skipped, errors]
     */
    private function migrateBackups(SymfonyStyle $io, string $tenantName, int $tenantId, bool $deleteOriginals): array
    {
        $io->section('Backups');
        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        try {
            $conn = $this->getControlPlaneConnection();
            $backups = $conn->fetchAllAssociative(
                'SELECT id, filename, storage_path FROM tenant_backups'
                . " WHERE tenant_id = ? AND storage_type = 's3' AND status = 'completed'",
                [$tenantId],
            );
        } catch (Exception $e) {
            $io->error('Failed to query backups: ' . $e->getMessage());

            return [0, 0, 1];
        }

        foreach ($backups as $backup) {
            $storagePath = (string) $backup['storage_path'];
            $filename = (string) $backup['filename'];
            $backupId = (int) $backup['id'];

            // Old path: adapter had 'backups' prefix, so stored as {tenantName}/{filename}
            $sourcePath = 'backups/' . $storagePath;
            $targetPath = $tenantName . '/' . Constants::BACKUP_DIR . '/' . $filename;

            try {
                if ($this->copyFile($io, $sourcePath, $targetPath, $deleteOriginals)) {
                    $conn->update('tenant_backups', [
                        'storage_path' => $targetPath,
                    ], ['id' => $backupId]);
                    $migrated++;
                } else {
                    $skipped++;
                }
            } catch (FilesystemException | Exception $e) {
                $errors++;
                $this->logger->error('Failed to migrate backup', [
                    'tenant' => $tenantName,
                    'backup_id' => $backupId,
                    'source' => $sourcePath,
                    'target' => $targetPath,
                    'exception' => $e,
                ]);
                $io->writeln(sprintf('  <error>Error</error> %s: %s', $sourcePath, $e->getMessage()));
            }
        }

        $io->writeln(sprintf('  Backups — Migrated: %d, Skipped: %d, Errors: %d', $migrated, $skipped, $errors));

        return [$migrated, $skipped, $errors];
    }

    /**
     * @return array{int, int, int} [migrated, skipped, errors]
     */
    private function migrateImages(SymfonyStyle $io, string $tenantName, bool $deleteOriginals): array
    {
        $io->section('Images');
        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        try {
            $this->tenantConnectionSwitcher->switchConnection($tenantName);
            /** @var Connection $conn */
            $conn = $this->connectionRegistry->getConnection();
            $paths = $conn->fetchFirstColumn('SELECT path FROM media');
        } catch (\Throwable $e) {
            $io->error('Failed to query media: ' . $e->getMessage());

            return [0, 0, 1];
        }

        foreach ($paths as $path) {
            $sourcePath = 'images/' . $tenantName . '/' . $path;
            $targetPath = $tenantName . '/' . Constants::IMAGE_DIR . '/' . $path;

            try {
                if ($this->copyFile($io, $sourcePath, $targetPath, $deleteOriginals)) {
                    $migrated++;
                } else {
                    $skipped++;
                }
            } catch (FilesystemException $e) {
                $errors++;
                $this->logger->error('Failed to migrate image', [
                    'tenant' => $tenantName,
                    'source' => $sourcePath,
                    'target' => $targetPath,
                    'exception' => $e,
                ]);
                $io->writeln(sprintf('  <error>Error</error> %s: %s', $sourcePath, $e->getMessage()));
            }
        }

        $io->writeln(sprintf('  Images — Migrated: %d, Skipped: %d, Errors: %d', $migrated, $skipped, $errors));

        return [$migrated, $skipped, $errors];
    }

    /**
     * @return array{int, int, int} [migrated, skipped, errors]
     */
    private function migrateInvoices(SymfonyStyle $io, string $tenantName, bool $deleteOriginals): array
    {
        $io->section('Invoices');
        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        try {
            $this->tenantConnectionSwitcher->switchConnection($tenantName);
            /** @var Connection $conn */
            $conn = $this->connectionRegistry->getConnection();
            $companyId = $conn->fetchOne('SELECT ruc FROM empresa LIMIT 1');
        } catch (\Throwable $e) {
            $io->error('Failed to query empresa: ' . $e->getMessage());

            return [0, 0, 1];
        }

        if ($companyId === false || $companyId === '') {
            $io->writeln('  No empresa/RUC found, skipping invoices');

            return [0, 0, 0];
        }

        $companyId = (string) $companyId;

        try {
            $files = $this->defaultFilesystem->listContents($companyId . '/')
                ->filter(static fn (StorageAttributes $attr): bool => $attr->isFile())
                ->toArray();
        } catch (FilesystemException $e) {
            $io->error('Failed to list invoice files: ' . $e->getMessage());

            return [0, 0, 1];
        }

        foreach ($files as $file) {
            $sourcePath = $file->path();
            $filename = basename($sourcePath);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $docFullName = str_starts_with($nameWithoutExt, 'R-')
                ? substr($nameWithoutExt, 2)
                : $nameWithoutExt;
            $targetPath = $tenantName . '/' . Constants::INVOICE_DIR . '/' . $docFullName . '/' . $filename;

            try {
                if ($this->copyFile($io, $sourcePath, $targetPath, $deleteOriginals)) {
                    $migrated++;
                } else {
                    $skipped++;
                }
            } catch (FilesystemException $e) {
                $errors++;
                $this->logger->error('Failed to migrate invoice file', [
                    'tenant' => $tenantName,
                    'source' => $sourcePath,
                    'target' => $targetPath,
                    'exception' => $e,
                ]);
                $io->writeln(sprintf('  <error>Error</error> %s: %s', $sourcePath, $e->getMessage()));
            }
        }

        $io->writeln(sprintf('  Invoices — Migrated: %d, Skipped: %d, Errors: %d', $migrated, $skipped, $errors));

        return [$migrated, $skipped, $errors];
    }

    /**
     * @return bool true if file was copied, false if skipped
     *
     * @throws FilesystemException
     */
    private function copyFile(SymfonyStyle $io, string $sourcePath, string $targetPath, bool $deleteOriginals): bool
    {
        if ($this->defaultFilesystem->fileExists($targetPath)) {
            $io->writeln(
                sprintf('  <comment>Skipped</comment> %s (target exists)', $targetPath),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        if (!$this->defaultFilesystem->fileExists($sourcePath)) {
            $io->writeln(
                sprintf('  <comment>Skipped</comment> %s (source not found)', $sourcePath),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        $this->defaultFilesystem->copy($sourcePath, $targetPath);

        if ($deleteOriginals) {
            $this->defaultFilesystem->delete($sourcePath);
        }

        $io->writeln(
            sprintf('  <info>Migrated</info> %s → %s', $sourcePath, $targetPath),
            OutputInterface::VERBOSITY_VERBOSE,
        );

        return true;
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
