<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantSchemaAuditCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantSchemaAuditCommandTest extends TestCase
{
    public function testBuildSchemaSnapshotNormalizesAndFiltersRows(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')
            ->willReturn(['menu', 'doctrine_migration_versions', 'MENU']);
        $connection->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        'table_name' => 'menu',
                        'column_name' => 'ID',
                        'column_type' => 'INT(11)',
                        'is_nullable' => 'NO',
                        'column_default' => null,
                        'extra' => 'AUTO_INCREMENT',
                        'collation_name' => '',
                    ],
                ],
                [
                    [
                        'table_name' => 'menu',
                        'non_unique' => 0,
                        'index_type' => 'BTREE',
                        'column_list' => 'id',
                    ],
                ],
                [
                    [
                        'table_name' => 'menu',
                        'constraint_type' => 'FOREIGN KEY',
                        'column_list' => 'id',
                        'referenced_table_name' => 'cliente',
                        'referenced_column_list' => 'id',
                        'update_rule' => 'NO ACTION',
                        'delete_rule' => 'RESTRICT',
                        'check_clause' => '',
                    ],
                    [
                        'table_name' => 'menu',
                        'constraint_type' => 'CHECK',
                        'column_list' => '',
                        'referenced_table_name' => '',
                        'referenced_column_list' => '',
                        'update_rule' => '',
                        'delete_rule' => '',
                        'check_clause' => ' amount > 0 ',
                    ],
                ]
            );

        $command = new TenantSchemaAuditCommand($connection);

        $snapshot = $this->invokePrivate($command, 'buildSchemaSnapshot');

        self::assertSame(['menu'], $snapshot['tables']);
        self::assertContains('menu.id|int(11)|no|null|auto_increment|', $snapshot['columns']);
        self::assertContains('menu|0|btree|id', $snapshot['indexes']);
        self::assertContains('FK|menu|id|cliente|id|RESTRICT|RESTRICT', $snapshot['constraints']);
        self::assertContains('CHECK|menu|amount > 0', $snapshot['constraints']);
    }

    public function testDiffSummaryAndHasDrift(): void
    {
        $command = new TenantSchemaAuditCommand($this->createMock(Connection::class));
        $this->setPrivate($command, 'baselineTenant', 'tenant_a');

        $baseline = [
            'tables' => ['menu'],
            'columns' => ['menu.id|int(11)|no|null||'],
            'indexes' => ['menu|0|btree|id'],
            'constraints' => ['PRIMARY KEY|menu|id'],
        ];
        $current = [
            'tables' => ['menu', 'unidad'],
            'columns' => ['menu.id|int(11)|no|null||'],
            'indexes' => [],
            'constraints' => ['PRIMARY KEY|menu|id', 'CHECK|menu|amount > 0'],
        ];

        $drift = $this->invokePrivate($command, 'diffSnapshot', [$baseline, $current]);

        self::assertTrue($this->invokePrivate($command, 'hasDrift', [$drift]));
        self::assertSame(['unidad'], $drift['tables_extra']);
        self::assertSame(['menu|0|btree|id'], $drift['indexes_missing']);

        $summary = $this->invokePrivate($command, 'buildDriftSummary', [$drift]);
        self::assertSame(3, $summary['critical_total']);
        self::assertSame('tenant_a', $summary['baseline_tenant']);
    }

    public function testNormalizeFkRule(): void
    {
        $command = new TenantSchemaAuditCommand($this->createMock(Connection::class));

        self::assertSame('RESTRICT', $this->invokePrivate($command, 'normalizeFkRule', ['NO ACTION']));
        self::assertSame('CASCADE', $this->invokePrivate($command, 'normalizeFkRule', ['cascade']));
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

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        $refProperty = $reflection->getProperty($property);
        $refProperty->setValue($object, $value);
    }
}
