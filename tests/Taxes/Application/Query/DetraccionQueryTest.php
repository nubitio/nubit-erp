<?php

declare(strict_types=1);

namespace App\Tests\Taxes\Application\Query;

use App\Taxes\Application\Query\DetraccionQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DetraccionQueryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $pdo = $this->connection->getNativeConnection();
        self::assertInstanceOf(\PDO::class, $pdo);
        $pdo->sqliteCreateFunction('DATE_FORMAT', function (?string $date, string $format): ?string {
            if (null === $date) {
                return null;
            }
            $phpFormat = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

            return false !== ($ts = strtotime($date)) ? date($phpFormat, $ts) : null;
        });

        $this->connection->executeStatement('
            CREATE TABLE tax_document (
                tax_document_id VARCHAR(36) NOT NULL PRIMARY KEY,
                document_type VARCHAR(10) NOT NULL,
                series VARCHAR(10) NOT NULL,
                number VARCHAR(20) NOT NULL,
                issue_date DATE NOT NULL,
                customer_id INT DEFAULT NULL,
                total DECIMAL(10,2) NOT NULL DEFAULT 0,
                detraction_code VARCHAR(10) DEFAULT NULL,
                detraction_percent DECIMAL(10,2) DEFAULT NULL,
                detraction_amount DECIMAL(10,2) DEFAULT NULL,
                detraction_deposited_at DATETIME DEFAULT NULL,
                void_ticket VARCHAR(255) DEFAULT NULL,
                void_correlation VARCHAR(255) DEFAULT NULL,
                taxable_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                igv_amount DECIMAL(10,2) NOT NULL DEFAULT 0
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE cliente (
                id INT NOT NULL PRIMARY KEY,
                razon_social VARCHAR(255) NOT NULL
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE sunat_catalog (
                id INT NOT NULL PRIMARY KEY,
                catalog VARCHAR(10) NOT NULL,
                code VARCHAR(10) NOT NULL,
                description VARCHAR(255) DEFAULT NULL
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE tenant_settings (
                id INT NOT NULL PRIMARY KEY,
                detraction_account VARCHAR(30) DEFAULT NULL
            )
        ');
    }

    public function testPendienteAndDepositadoSums(): void
    {
        $this->connection->insert('tenant_settings', ['id' => 1, 'detraction_account' => '00-123-456789']);

        $this->connection->insert('cliente', ['id' => 1, 'razon_social' => 'Cliente A']);
        $this->connection->insert('cliente', ['id' => 2, 'razon_social' => 'Cliente B']);

        // One deposited, one pending
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd1',
            'document_type' => '01',
            'series' => 'F001',
            'number' => '100',
            'issue_date' => '2026-06-05',
            'customer_id' => 1,
            'total' => 5900,
            'detraction_code' => '001',
            'detraction_percent' => 4,
            'detraction_amount' => 200,
            'detraction_deposited_at' => '2026-06-10 10:00:00',
            'taxable_amount' => 5000,
            'igv_amount' => 900,
        ]);
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd2',
            'document_type' => '01',
            'series' => 'F001',
            'number' => '101',
            'issue_date' => '2026-06-15',
            'customer_id' => 2,
            'total' => 3776,
            'detraction_code' => '005',
            'detraction_percent' => 12,
            'detraction_amount' => 384,
            'taxable_amount' => 3200,
            'igv_amount' => 576,
        ]);

        $query = new DetraccionQuery($this->connection);
        $result = $query->forPeriod('202606');

        self::assertSame(200.0, $result['depositado']);
        self::assertSame(384.0, $result['pendiente']);
        self::assertSame('00-123-456789', $result['cuenta']);
        self::assertCount(2, $result['items']);
    }

    public function testEmptyPeriodReturnsEmptyItems(): void
    {
        $this->connection->insert('tenant_settings', ['id' => 1]);

        $query = new DetraccionQuery($this->connection);
        $result = $query->forPeriod('202601');

        self::assertCount(0, $result['items']);
        self::assertSame(0.0, $result['depositado']);
        self::assertSame(0.0, $result['pendiente']);
    }

    public function testVoidedDetraccionExcluded(): void
    {
        $this->connection->insert('tenant_settings', ['id' => 1]);

        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd1',
            'document_type' => '01',
            'series' => 'F001',
            'number' => '100',
            'issue_date' => '2026-06-05',
            'customer_id' => 1,
            'total' => 5900,
            'detraction_code' => '001',
            'detraction_percent' => 4,
            'detraction_amount' => 200,
            'void_ticket' => 'T123',
            'taxable_amount' => 5000,
            'igv_amount' => 900,
        ]);

        $query = new DetraccionQuery($this->connection);
        $result = $query->forPeriod('202606');

        self::assertCount(0, $result['items']);
        self::assertSame(0.0, $result['pendiente']);
    }
}
