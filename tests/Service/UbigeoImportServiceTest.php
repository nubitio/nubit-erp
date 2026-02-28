<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UbigeoImportService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class UbigeoImportServiceTest extends TestCase
{
    public function testImportThrowsWhenFileIsNotReadable(): void
    {
        $service = new UbigeoImportService($this->createMock(Connection::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No se puede leer el archivo');

        $service->import('/tmp/non-existent-ubigeo.csv');
    }

    public function testImportLoadsCsvInBatchesAndReEnablesChecks(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ubigeo-test-');
        self::assertNotFalse($tmpFile);
        file_put_contents(
            $tmpFile,
            "codigo,departamento,provincia,distrito\n"
            . "010101,AMAZONAS,CHACHAPOYAS,CHACHAPOYAS\n"
            . "010102,AMAZONAS,CHACHAPOYAS,ASUNCION\n"
            . "010103,AMAZONAS,CHACHAPOYAS,BALSAS\n"
        );

        $executedSql = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql) use (&$executedSql): int {
                $executedSql[] = $sql;

                return 1;
            }
        );

        $service = new UbigeoImportService($connection);

        $progress = [];
        $count = $service->import(
            $tmpFile,
            skipHeader: true,
            batchSize: 2,
            disableChecks: true,
            progressCallback: static function (int $batchCount) use (&$progress): void {
                $progress[] = $batchCount;
            }
        );

        unlink($tmpFile);

        self::assertSame(3, $count);
        self::assertSame([2, 1], $progress);
        self::assertContains('SET foreign_key_checks = 0', $executedSql);
        self::assertContains('SET unique_checks = 0', $executedSql);
        self::assertContains('SET foreign_key_checks = 1', $executedSql);
        self::assertContains('SET unique_checks = 1', $executedSql);
    }

    public function testImportRollsBackAndRestoresChecksWhenInsertFails(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ubigeo-fail-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "010101,AMAZONAS,CHACHAPOYAS,CHACHAPOYAS\n");

        $executedSql = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql) use (&$executedSql): int {
                $executedSql[] = $sql;

                if (str_starts_with($sql, 'INSERT INTO ubigeo')) {
                    throw new \RuntimeException('insert failed');
                }

                return 1;
            }
        );

        $service = new UbigeoImportService($connection);

        try {
            $service->import($tmpFile, disableChecks: true);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $exception) {
            self::assertSame('insert failed', $exception->getMessage());
            self::assertContains('SET foreign_key_checks = 1', $executedSql);
            self::assertContains('SET unique_checks = 1', $executedSql);
        } finally {
            unlink($tmpFile);
        }
    }
}
