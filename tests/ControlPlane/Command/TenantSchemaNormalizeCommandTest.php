<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantSchemaNormalizeCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class TenantSchemaNormalizeCommandTest extends TestCase
{
    public function testBuildMenuIdNormalizationAddsPrimaryAndModifyStatements(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): int {
                if (str_contains($sql, 'information_schema.tables')) {
                    return 1;
                }
                if (str_contains($sql, 'column_name = :column')) {
                    return 1;
                }
                if (str_contains($sql, "constraint_type = 'PRIMARY KEY'")) {
                    return 0;
                }

                return 0;
            });
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'data_type' => 'int',
                'is_nullable' => 'YES',
                'extra' => '',
                'column_key' => '',
            ]);

        $command = new TenantSchemaNormalizeCommand($connection);

        $sql = $this->invokePrivate($command, 'buildMenuIdNormalization');

        self::assertSame(
            [
                'ALTER TABLE menu ADD PRIMARY KEY (id)',
                'ALTER TABLE menu MODIFY id INT NOT NULL AUTO_INCREMENT',
            ],
            $sql,
        );
    }

    public function testBuildUnidadCodeNormalizationIncludesFallbacksAndNotNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params): int {
                if (str_contains($sql, 'information_schema.tables')) {
                    return ($params['table'] ?? '') === 'unidad' ? 1 : 0;
                }
                if (str_contains($sql, 'information_schema.columns')) {
                    return match ($params['column'] ?? '') {
                        'code' => 0,
                        'codigo', 'name' => 1,
                        default => 0,
                    };
                }

                return 0;
            });
        $connection->method('fetchAssociative')->willReturn(['is_nullable' => 'YES']);

        $command = new TenantSchemaNormalizeCommand($connection);
        $sql = $this->invokePrivate($command, 'buildUnidadCodeNormalization');

        self::assertContains('ALTER TABLE unidad ADD code VARCHAR(255) DEFAULT NULL', $sql);
        self::assertContains("UPDATE unidad SET code = NULLIF(TRIM(codigo), '') WHERE code IS NULL", $sql);
        self::assertContains("UPDATE unidad SET code = NULLIF(TRIM(name), '') WHERE code IS NULL", $sql);
        self::assertContains("UPDATE unidad SET code = 'NIU' WHERE code IS NULL", $sql);
        self::assertContains('ALTER TABLE unidad MODIFY code VARCHAR(255) NOT NULL', $sql);
    }

    public function testBuildTypeOnlyAlterReturnsStatementWhenTypeChanges(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'column_type' => 'char(11)',
                'is_nullable' => 'NO',
                'collation_name' => 'utf8mb4_general_ci',
            ]);

        $command = new TenantSchemaNormalizeCommand($connection);

        $sql = $this->invokePrivate($command, 'buildTypeOnlyAlter', [
            'cliente',
            'ruc',
            'varchar(11)',
            'utf8mb4_unicode_ci',
        ]);

        self::assertSame('ALTER TABLE `cliente` MODIFY `ruc` VARCHAR(11) COLLATE utf8mb4_unicode_ci NOT NULL', $sql);
    }

    /**
     * @param list<mixed> $args
     */
    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionObject($object);
        $refMethod = $reflection->getMethod($method);

        return $refMethod->invokeArgs($object, $args);
    }
}
