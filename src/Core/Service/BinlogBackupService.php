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

final readonly class BinlogBackupService
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private FilesystemOperator $defaultFilesystem,
        private LoggerInterface $logger,
        #[Autowire(env: 'BACKUP_DATABASE_URL')]
        private string $backupDatabaseUrl,
    ) {
    }

    /**
     * @return array{backed_up: int, skipped: bool, message: string}
     * @throws Exception
     */
    public function backupBinlogs(): array
    {
        $conn = $this->getControlPlaneConnection();
        $dbConn = $this->getProvisioningConnection();

        $logBin = $dbConn->fetchAssociative("SHOW VARIABLES LIKE 'log_bin'");
        if ($logBin === false || strtolower((string)($logBin['Value'] ?? '')) !== 'on') {
            return ['backed_up' => 0, 'skipped' => true, 'message' => 'Binary logging is not enabled'];
        }

        $masterStatus = $this->getCurrentMasterStatus();
        if ($masterStatus === []) {
            return ['backed_up' => 0, 'skipped' => true, 'message' => 'Could not retrieve master status'];
        }

        $lastBackup = $this->getLastBackedUpPosition();

        if (
            $lastBackup !== []
            && $lastBackup['binlog_file'] === $masterStatus['file']
            && (int)$lastBackup['binlog_position'] === (int)$masterStatus['position']
        ) {
            return ['backed_up' => 0, 'skipped' => true, 'message' => 'No new binlog data since last backup'];
        }

        $dbConn->executeStatement('FLUSH BINARY LOGS');

        $binaryLogs = $dbConn->fetchAllAssociative('SHOW BINARY LOGS');

        $startFile = $lastBackup !== [] ? $lastBackup['binlog_file'] : null;
        $currentFile = $masterStatus['file'];

        $filesToBackup = [];
        $pastStart = ($startFile === null);
        foreach ($binaryLogs as $log) {
            $logName = (string)$log['Log_name'];
            if (!$pastStart) {
                if ($logName === $startFile) {
                    $pastStart = true;
                }
                continue;
            }
            if ($logName === $currentFile) {
                break;
            }
            $filesToBackup[] = $logName;
        }

        if ($filesToBackup === []) {
            return ['backed_up' => 0, 'skipped' => true, 'message' => 'No binlog files to backup'];
        }

        $params = $this->getConnectionParams();
        $localDir = 'var/binlog_backups';
        if (!is_dir($localDir)) {
            mkdir($localDir, 0o750, true);
        }

        $backedUp = 0;
        $dateDir = date('Y-m-d');

        foreach ($filesToBackup as $binlogFile) {
            $backupId = $this->insertBinlogRecord($conn, $binlogFile);

            try {
                $localFile = sprintf('%s/%s', $localDir, $binlogFile);
                $this->copyBinlogFromServer($params, $localDir, $binlogFile);

                if (!file_exists($localFile)) {
                    throw new RuntimeException(sprintf('Binlog file %s was not downloaded', $binlogFile));
                }

                $gzFile = $localFile . '.gz';
                $this->compressFile($localFile, $gzFile);
                @unlink($localFile);

                $sizeBytes = (int)filesize($gzFile);
                $s3Filename = $binlogFile . '.gz';
                $s3Key = sprintf('%s/%s/%s', Constants::BINLOG_DIR, $dateDir, $s3Filename);

                $stream = fopen($gzFile, 'r');
                if ($stream === false) {
                    throw new RuntimeException('Cannot open compressed binlog for upload');
                }

                try {
                    $this->defaultFilesystem->writeStream($s3Key, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                @unlink($gzFile);

                $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
                $conn->update('binlog_backups', [
                    'binlog_position' => (int)$masterStatus['position'],
                    'filename' => $s3Filename,
                    'storage_path' => $s3Key,
                    'size_bytes' => $sizeBytes,
                    'status' => 'completed',
                    'completed_at' => $now,
                ], ['id' => $backupId]);

                $backedUp++;
            } catch (\Throwable $e) {
                $conn->update('binlog_backups', [
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    'completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $backupId]);

                $this->logger->error('Binlog backup failed for {file}: {error}', [
                    'file' => $binlogFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'backed_up' => $backedUp,
            'skipped' => false,
            'message' => sprintf('Backed up %d binlog file(s)', $backedUp),
        ];
    }

    /**
     * @return array{binlog_file: string, binlog_position: int}|array{}
     * @throws Exception
     */
    public function getLastBackedUpPosition(): array
    {
        $row = $this->getControlPlaneConnection()->fetchAssociative(
            'SELECT binlog_file, binlog_position FROM binlog_backups WHERE status = ? ORDER BY id DESC LIMIT 1',
            ['completed'],
        );

        if ($row === false) {
            return [];
        }

        return [
            'binlog_file' => (string)$row['binlog_file'],
            'binlog_position' => (int)$row['binlog_position'],
        ];
    }

    /**
     * @return array{file: string, position: int}|array{}
     */
    public function getCurrentMasterStatus(): array
    {
        $dbConn = $this->getProvisioningConnection();
        $row = $dbConn->fetchAssociative('SHOW MASTER STATUS');

        if ($row === false) {
            return [];
        }

        return [
            'file' => (string)$row['File'],
            'position' => (int)$row['Position'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function listBinlogBackups(int $limit = 20): array
    {
        return $this->getControlPlaneConnection()->fetchAllAssociative(
            'SELECT id, binlog_file, binlog_position, filename, storage_path, size_bytes,
                    status, error_message, created_at, completed_at
             FROM binlog_backups
             ORDER BY created_at DESC
             LIMIT ?',
            [$limit],
            [ParameterType::INTEGER],
        );
    }

    /**
     * @throws Exception
     * @throws FilesystemException
     */
    public function deleteBinlogBackup(int $id): void
    {
        $conn = $this->getControlPlaneConnection();
        $backup = $conn->fetchAssociative('SELECT * FROM binlog_backups WHERE id = ?', [$id]);

        if ($backup === false) {
            throw new RuntimeException(sprintf('Binlog backup #%d not found.', $id));
        }

        $storagePath = (string)$backup['storage_path'];
        if ($storagePath !== '') {
            try {
                $this->defaultFilesystem->delete($storagePath);
            } catch (FilesystemException $e) {
                $this->logger->warning('Could not delete binlog backup file from S3', [
                    'backup_id' => $id,
                    'storage_path' => $storagePath,
                    'exception' => $e,
                ]);
            }
        }

        $conn->delete('binlog_backups', ['id' => $id]);
    }

    /**
     * @return array{last_backup_at: string|null, total_count: int, total_size_bytes: int}
     * @throws Exception
     */
    public function getBinlogBackupSummary(): array
    {
        $conn = $this->getControlPlaneConnection();

        if (!$conn->createSchemaManager()->tablesExist(['binlog_backups'])) {
            return [
                'last_backup_at' => null,
                'total_count' => 0,
                'total_size_bytes' => 0,
            ];
        }

        $row = $conn->fetchAssociative(
            'SELECT MAX(completed_at) AS last_backup_at,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(size_bytes), 0) AS total_size_bytes
             FROM binlog_backups
             WHERE status = ?',
            ['completed'],
        );

        if ($row === false) {
            return [
                'last_backup_at' => null,
                'total_count' => 0,
                'total_size_bytes' => 0,
            ];
        }

        return [
            'last_backup_at' => $row['last_backup_at'],
            'total_count' => (int)$row['total_count'],
            'total_size_bytes' => (int)$row['total_size_bytes'],
        ];
    }

    private function insertBinlogRecord(Connection $conn, string $binlogFile): int
    {
        $conn->insert('binlog_backups', [
            'binlog_file' => $binlogFile,
            'binlog_position' => 0,
            'filename' => '',
            'storage_path' => '',
            'size_bytes' => 0,
            'status' => 'pending',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int)$conn->lastInsertId();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function copyBinlogFromServer(array $params, string $resultDir, string $binlogFile): void
    {
        $host = (string)($params['host'] ?? 'localhost');
        $port = (string)($params['port'] ?? '3306');
        $user = (string)($params['user'] ?? 'root');
        $password = (string)($params['password'] ?? '');

        $cmd = [
            'mariadb-binlog',
            '--read-from-remote-server',
            '--raw',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
        ];

        if ($password !== '') {
            $cmd[] = '--password=' . $password;
        }

        $cmd[] = '--result-file=' . $resultDir . '/';
        $cmd[] = $binlogFile;

        $process = new Process($cmd);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('mariadb-binlog failed: ' . $process->getErrorOutput());
        }
    }

    private function compressFile(string $input, string $output): void
    {
        $process = new Process(['gzip', '-c', $input]);
        $process->setTimeout(120);

        $outStream = fopen($output, 'w');
        if ($outStream === false) {
            throw new RuntimeException('Cannot create gzip output file');
        }

        $process->run(function (string $type, string $data) use ($outStream): void {
            if ($type === Process::OUT) {
                fwrite($outStream, $data);
            }
        });
        fclose($outStream);

        if (!$process->isSuccessful()) {
            @unlink($output);
            throw new RuntimeException('gzip compression failed: ' . $process->getErrorOutput());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getConnectionParams(): array
    {
        $url = $this->backupDatabaseUrl;

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

    private function getProvisioningConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('tenant');
    }
}
