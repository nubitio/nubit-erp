<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PurchaseSoftDeleteRetentionSqliteTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        $this->connection->executeStatement(
            'CREATE TABLE goods_receipts (id TEXT PRIMARY KEY NOT NULL, deleted_at DATETIME DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE goods_receipt_items (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, goods_receipt_id TEXT NOT NULL, FOREIGN KEY(goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE)',
        );
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testSoftDeletingPurchaseKeepsPurchaseItemForeignKeysIntact(): void
    {
        $this->connection->insert('goods_receipts', [
            'id' => 'purchase-1',
            'deleted_at' => null,
        ]);
        $this->connection->insert('goods_receipt_items', [
            'goods_receipt_id' => 'purchase-1',
        ]);

        $affectedRows = $this->connection->update(
            'goods_receipts',
            [
                'deleted_at' => '2026-03-23 00:00:00',
            ],
            [
                'id' => 'purchase-1',
            ],
        );

        self::assertSame(1, $affectedRows);
        self::assertSame(
            '2026-03-23 00:00:00',
            $this->connection->fetchOne("SELECT deleted_at FROM goods_receipts WHERE id = 'purchase-1'"),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM goods_receipt_items WHERE goods_receipt_id = 'purchase-1'",
            ),
        );
    }
}
