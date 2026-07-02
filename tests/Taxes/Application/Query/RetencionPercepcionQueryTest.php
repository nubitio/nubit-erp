<?php

declare(strict_types=1);

namespace App\Tests\Taxes\Application\Query;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use App\Taxes\Application\Query\RetencionPercepcionQuery;
use PHPUnit\Framework\TestCase;

final class RetencionPercepcionQueryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $pdo = $this->connection->getNativeConnection();
        $pdo->sqliteCreateFunction('DATE_FORMAT', function (?string $date, string $format): ?string {
            if (null === $date) {
                return null;
            }
            $phpFormat = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

            return false !== ($ts = strtotime($date)) ? date($phpFormat, $ts) : null;
        });

        $this->connection->executeStatement('
            CREATE TABLE retention_percepcion (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                type VARCHAR(20) NOT NULL,
                direction VARCHAR(20) NOT NULL,
                counterparty_name VARCHAR(255) NOT NULL,
                document_reference VARCHAR(50) NOT NULL,
                rate DECIMAL(8,4) NOT NULL DEFAULT 0,
                base_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                period_date DATE NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ');
    }

    public function testReturnsItemsForPeriod(): void
    {
        $this->connection->insert('retention_percepcion', [
            'id' => 'r1', 'type' => 'retencion', 'direction' => 'sufrida',
            'counterparty_name' => 'Corp A', 'document_reference' => 'F001-100',
            'rate' => 3.0, 'base_amount' => 5000, 'amount' => 150,
            'period_date' => '2026-06-15',
            'created_at' => '2026-06-15 00:00:00', 'updated_at' => '2026-06-15 00:00:00',
        ]);

        $query = new RetencionPercepcionQuery($this->connection);
        $result = $query->forPeriod('202606');

        self::assertCount(1, $result['items']);
        self::assertSame('retencion', $result['items'][0]['type']);
        self::assertSame(150.0, $result['items'][0]['amount']);
    }

    public function testEmptyPeriod(): void
    {
        $query = new RetencionPercepcionQuery($this->connection);
        $result = $query->forPeriod('202601');

        self::assertCount(0, $result['items']);
    }

    public function testFiltersByPeriod(): void
    {
        $this->connection->insert('retention_percepcion', [
            'id' => 'r1', 'type' => 'retencion', 'direction' => 'sufrida',
            'counterparty_name' => 'Corp A', 'document_reference' => 'F001-100',
            'rate' => 3.0, 'base_amount' => 5000, 'amount' => 150,
            'period_date' => '2026-06-15',
            'created_at' => '2026-06-15 00:00:00', 'updated_at' => '2026-06-15 00:00:00',
        ]);
        $this->connection->insert('retention_percepcion', [
            'id' => 'r2', 'type' => 'percepcion', 'direction' => 'sufrida',
            'counterparty_name' => 'Corp B', 'document_reference' => 'F-200',
            'rate' => 2.0, 'base_amount' => 1000, 'amount' => 20,
            'period_date' => '2026-05-01',
            'created_at' => '2026-05-01 00:00:00', 'updated_at' => '2026-05-01 00:00:00',
        ]);

        $result = (new RetencionPercepcionQuery($this->connection))->forPeriod('202606');

        self::assertCount(1, $result['items']);
        self::assertSame('Corp A', $result['items'][0]['counterparty_name']);
    }
}
