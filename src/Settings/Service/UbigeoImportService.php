<?php

declare(strict_types=1);

namespace App\Settings\Service;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class UbigeoImportService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param callable(int):void|null $progressCallback Callback invoked after each batch.
     *
     * @throws Throwable
     */
    public function import(
        string $filePath,
        string $delimiter = ',',
        bool $skipHeader = false,
        int $batchSize = 200,
        bool $disableChecks = true,
        ?callable $progressCallback = null,
    ): int {
        if (!is_readable($filePath)) {
            throw new InvalidArgumentException(sprintf('No se puede leer el archivo: %s', $filePath));
        }

        $batchSize = max(1, $batchSize);
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $checksDisabled = false;

        try {
            if ($disableChecks) {
                $this->connection->executeStatement('SET foreign_key_checks = 0');
                $this->connection->executeStatement('SET unique_checks = 0');
                $checksDisabled = true;
            }

            if ($skipHeader) {
                fgetcsv($handle, 0, $delimiter, escape: '\\');
            }

            $this->connection->beginTransaction();

            $batch = [];
            $count = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, escape: '\\')) !== false) {
                if (count($row) < 4) {
                    continue;
                }

                // Skip blank rows (e.g. a trailing ",,," line): an empty code
                // would be inserted as a PK and break IRI generation downstream.
                $code = trim((string)$row[0]);
                if ($code === '') {
                    continue;
                }

                $batch[] = [
                    'code' => $code,
                    'department' => trim((string)$row[1]),
                    'province' => trim((string)$row[2]),
                    'district' => trim((string)$row[3]),
                ];

                if (count($batch) === $batchSize) {
                    $this->insertBatch($batch);
                    $count += count($batch);

                    if ($progressCallback !== null) {
                        $progressCallback(count($batch));
                    }

                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->insertBatch($batch);
                $count += count($batch);

                if ($progressCallback !== null) {
                    $progressCallback(count($batch));
                }
            }

            $this->connection->commit();

            return $count;
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        } finally {
            if ($checksDisabled) {
                $this->connection->executeStatement('SET foreign_key_checks = 1');
                $this->connection->executeStatement('SET unique_checks = 1');
            }

            fclose($handle);
        }
    }

    /**
     * @param array<int, array{code:string, department:string, province:string, district:string}> $rows
     *
     * @throws Exception
     */
    private function insertBatch(array $rows): void
    {
        $placeholders = [];
        $params = [];

        foreach ($rows as $row) {
            $placeholders[] = '(?, ?, ?, ?)';
            $params[] = $row['code'];
            $params[] = $row['department'];
            $params[] = $row['province'];
            $params[] = $row['district'];
        }

        $sql = sprintf(
            'INSERT INTO ubigeo (codigo, departamento, provincia, distrito) VALUES %s
             ON DUPLICATE KEY UPDATE
               departamento = VALUES(departamento),
               provincia    = VALUES(provincia),
               distrito     = VALUES(distrito)',
            implode(',', $placeholders)
        );

        $this->connection->executeStatement($sql, $params);
    }
}
