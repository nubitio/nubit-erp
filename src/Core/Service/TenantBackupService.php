<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Core\Util\Constants;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final readonly class TenantBackupService
{
    public function __construct(
        private TenantManager $tenantManager,
        private ConnectionRegistry $connectionRegistry,
        private FilesystemOperator $defaultFilesystem,
        private LoggerInterface $logger,
        #[Autowire(env: 'PROVISIONING_DATABASE_URL')]
        private string $provisioningDatabaseUrl,
    ) {
    }

    /**
     * @return array{id: int, filename: string, storage_path: string, size_bytes: int, storage_type: string}
     * @throws Exception
     */
    public function backup(string $tenantName, bool $uploadToS3 = true): array
    {
        $tenant = $this->tenantManager->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new RuntimeException(sprintf('Tenant "%s" not found.', $tenantName));
        }

        $dbName = (string)($tenant['connection'] ?? $tenantName);
        $tenantId = (int)$tenant['id'];
        $timestamp = date('Ymd_His');
        $filename = sprintf('%s_%s.sql.gz', $dbName, $timestamp);
        $localDir = 'var/backups';
        $localPath = sprintf('%s/%s', $localDir, $filename);

        if (!is_dir($localDir)) {
            mkdir($localDir, 0o750, true);
        }

        $conn = $this->getControlPlaneConnection();
        $conn->insert('tenant_backups', [
            'tenant_id' => $tenantId,
            'filename' => $filename,
            'storage_path' => '',
            'size_bytes' => 0,
            'storage_type' => $uploadToS3 ? 's3' : 'local',
            'status' => 'pending',
            'created_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);
        $backupId = (int)$conn->lastInsertId();

        try {
            $this->executeDump($dbName, $localPath);

            $sizeBytes = (int)filesize($localPath);
            $storagePath = $localPath;

            if ($uploadToS3) {
                $s3Key = sprintf('%s/%s/%s', $tenantName, Constants::BACKUP_DIR, $filename);
                $stream = fopen($localPath, 'r');
                if ($stream === false) {
                    throw new RuntimeException('Cannot open backup file for upload.');
                }
                try {
                    $this->defaultFilesystem->writeStream($s3Key, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $storagePath = $s3Key;

                @unlink($localPath);
            }

            $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');
            $conn->update('tenant_backups', [
                'storage_path' => $storagePath,
                'size_bytes' => $sizeBytes,
                'status' => 'completed',
                'completed_at' => $now,
            ], ['id' => $backupId]);

            return [
                'id' => $backupId,
                'filename' => $filename,
                'storage_path' => $storagePath,
                'size_bytes' => $sizeBytes,
                'storage_type' => $uploadToS3 ? 's3' : 'local',
            ];
        } catch (\Throwable $e) {
            $conn->update('tenant_backups', [
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ], ['id' => $backupId]);

            @unlink($localPath);

            throw new RuntimeException(sprintf('Backup failed for "%s": %s', $tenantName, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function listBackups(string $tenantName, int $limit = 20): array
    {
        $tenant = $this->tenantManager->getTenantByName($tenantName);
        if ($tenant === null) {
            return [];
        }

        return $this->getControlPlaneConnection()->fetchAllAssociative(
            'SELECT id, tenant_id, filename, storage_path, size_bytes,
                    storage_type, status, error_message, created_at, completed_at
             FROM tenant_backups
             WHERE tenant_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [(int)$tenant['id'], $limit],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function listBackupsByTenantId(int $tenantId, int $limit = 20): array
    {
        return $this->getControlPlaneConnection()->fetchAllAssociative(
            'SELECT id, tenant_id, filename, storage_path, size_bytes,
                    storage_type, status, error_message, created_at, completed_at
             FROM tenant_backups
             WHERE tenant_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$tenantId, $limit],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    /**
     * @throws Exception
     */
    public function deleteBackup(int $backupId): void
    {
        $backup = $this->getBackup($backupId);
        $conn = $this->getControlPlaneConnection();

        if ($backup['storage_type'] === 's3') {
            $storagePath = (string)$backup['storage_path'];
            if ($storagePath !== '') {
                try {
                    $this->defaultFilesystem->delete($storagePath);
                } catch (FilesystemException $e) {
                    $this->logger->warning('Could not delete backup file from S3', [
                        'backup_id' => $backupId,
                        'storage_path' => $storagePath,
                        'exception' => $e,
                    ]);
                }
            }
        } elseif ($backup['storage_type'] === 'local') {
            $localPath = (string)$backup['storage_path'];
            if ($localPath !== '' && file_exists($localPath)) {
                @unlink($localPath);
            }
        }

        $conn->delete('tenant_backups', ['id' => $backupId]);
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function downloadBackup(int $backupId): string
    {
        $backup = $this->getBackup($backupId);

        if ($backup['storage_type'] === 'local') {
            $path = (string)$backup['storage_path'];
            if (!file_exists($path)) {
                throw new RuntimeException('Local backup file not found.');
            }

            return $path;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'backup_') . '.sql.gz';
        $stream = $this->defaultFilesystem->readStream((string)$backup['storage_path']);
        $local = fopen($tmpFile, 'w');
        if ($local === false) {
            throw new RuntimeException('Cannot create temp file for download.');
        }

        try {
            stream_copy_to_stream($stream, $local);
        } finally {
            fclose($local);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $tmpFile;
    }

    /**
     * @throws Exception
     */
    public function restore(int $backupId): void
    {
        $backup = $this->getBackup($backupId);

        if ($backup['status'] !== 'completed') {
            throw new RuntimeException('Cannot restore from a backup that is not completed.');
        }

        $conn = $this->getControlPlaneConnection();
        $tenant = $conn->fetchAssociative(
            'SELECT id, name, connection FROM tenants WHERE id = ?',
            [(int)$backup['tenant_id']],
        );
        if ($tenant === false) {
            throw new RuntimeException('Tenant not found for this backup.');
        }

        $dbName = (string)($tenant['connection'] ?: $tenant['name']);
        $localFile = $this->downloadBackup($backupId);

        try {
            $this->executeRestore($dbName, $localFile);
        } finally {
            if ($backup['storage_type'] === 's3' && file_exists($localFile)) {
                @unlink($localFile);
            }
        }

        $this->logger->info('Tenant restored from backup', [
            'tenant' => $tenant['name'],
            'backup_id' => $backupId,
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function getBackup(int $backupId): array
    {
        $backup = $this->getControlPlaneConnection()->fetchAssociative(
            'SELECT * FROM tenant_backups WHERE id = ?',
            [$backupId],
        );

        if ($backup === false) {
            throw new RuntimeException(sprintf('Backup #%d not found.', $backupId));
        }

        return $backup;
    }

    private function executeDump(string $dbName, string $outputPath): void
    {
        $params = $this->getConnectionParams();
        $host = (string)($params['host'] ?? 'localhost');
        $port = (string)($params['port'] ?? '3306');
        $user = (string)($params['user'] ?? 'root');
        $password = (string)($params['password'] ?? '');

        $dumpCmd = [
            'mariadb-dump',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
        ];

        if ($password !== '') {
            $dumpCmd[] = '--password=' . $password;
        }

        $dumpCmd = array_merge($dumpCmd, [
            '--single-transaction',
            '--routines',
            '--triggers',
            '--quick',
            $dbName,
        ]);

        $shellCmd = implode(' ', array_map('escapeshellarg', $dumpCmd))
            . ' | gzip > ' . escapeshellarg($outputPath);

        $process = Process::fromShellCommandline(
            'bash -o pipefail -c ' . escapeshellarg($shellCmd),
        );
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('mariadb-dump failed: ' . $process->getErrorOutput());
        }

        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('Backup file is empty or was not created.');
        }
    }

    private function executeRestore(string $dbName, string $filePath): void
    {
        $params = $this->getConnectionParams();
        $host = (string)($params['host'] ?? 'localhost');
        $port = (string)($params['port'] ?? '3306');
        $user = (string)($params['user'] ?? 'root');
        $password = (string)($params['password'] ?? '');

        $restoreCmd = [
            'mariadb',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
        ];

        if ($password !== '') {
            $restoreCmd[] = '--password=' . $password;
        }

        $restoreCmd[] = $dbName;

        $shellCmd = 'gunzip -c ' . escapeshellarg($filePath)
            . ' | ' . implode(' ', array_map('escapeshellarg', $restoreCmd));

        $process = Process::fromShellCommandline(
            'bash -o pipefail -c ' . escapeshellarg($shellCmd),
        );
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Restore failed: ' . $process->getErrorOutput());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getConnectionParams(): array
    {
        $url = $this->provisioningDatabaseUrl;

        if ($url !== '') {
            $parts = parse_url($url);
            if ($parts !== false) {
                return [
                    'host' => $parts['host'] ?? 'localhost',
                    'port' => $parts['port'] ?? 3306,
                    'user' => isset($parts['user']) ? urldecode($parts['user']) : 'root',
                    'password' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
                ];
            }
        }

        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        $params = $connection->getParams();

        return [
            'host' => $params['host'] ?? 'localhost',
            'port' => $params['port'] ?? 3306,
            'user' => $params['user'] ?? 'root',
            'password' => $params['password'] ?? '',
        ];
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
