<?php

declare(strict_types=1);

namespace App\Tests\Taxes\Application\Query;

use App\Taxes\Application\Query\TaxSummaryQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class TaxSummaryQueryTest extends TestCase
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
            $ts = strtotime($date);

            return false !== $ts ? date($phpFormat, $ts) : null;
        });

        $this->connection->executeStatement('
            CREATE TABLE tax_document (
                tax_document_id VARCHAR(36) NOT NULL PRIMARY KEY,
                document_type VARCHAR(10) NOT NULL,
                issue_date DATE NOT NULL,
                taxable_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                igv_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                void_ticket VARCHAR(255) DEFAULT NULL,
                void_correlation VARCHAR(255) DEFAULT NULL,
                detraction_amount DECIMAL(10,2) DEFAULT NULL,
                detraction_deposited_at DATETIME DEFAULT NULL
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE goods_receipts (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                status VARCHAR(30) NOT NULL DEFAULT \'POSTED\',
                issued_at DATETIME NOT NULL,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_total DECIMAL(12,2) NOT NULL DEFAULT 0
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE retention_percepcion (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                direction VARCHAR(20) NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                period_date DATE NOT NULL
            )
        ');
    }

    public function testComputeIgvForPeriod(): void
    {
        // Insert sales
        $this->connection->insert('tax_document', [
            'tax_document_id' => 's1',
            'document_type' => '01',
            'issue_date' => '2026-06-05',
            'taxable_amount' => 10000,
            'igv_amount' => 1800,
        ]);
        $this->connection->insert('tax_document', [
            'tax_document_id' => 's2',
            'document_type' => '03',
            'issue_date' => '2026-06-10',
            'taxable_amount' => 5000,
            'igv_amount' => 900,
        ]);

        // Insert purchase
        $this->connection->insert('goods_receipts', [
            'id' => 'p1',
            'status' => 'POSTED',
            'issued_at' => '2026-06-15 00:00:00',
            'subtotal' => 8000,
            'tax_total' => 1440,
        ]);

        // Insert retention sufrida
        $this->connection->insert('retention_percepcion', [
            'id' => 'r1',
            'direction' => 'sufrida',
            'amount' => 100,
            'period_date' => '2026-06-18',
        ]);

        $query = new TaxSummaryQuery($this->connection);
        $result = $query->compute('202606');

        self::assertSame(15000.0, $result['igvMensual']['debitoFiscal']['baseImponible']);
        self::assertSame(2700.0, $result['igvMensual']['debitoFiscal']['igv']);
        self::assertSame(8000.0, $result['igvMensual']['creditoFiscal']['baseImponible']);
        self::assertSame(1440.0, $result['igvMensual']['creditoFiscal']['igv']);
        self::assertSame(100.0, $result['igvMensual']['retencionesSufridas']);
        self::assertSame(1160.0, $result['igvMensual']['igvPorPagar']); // 2700 - 1440 - 100
        self::assertSame(1160.0, $result['kpi']['igvPorPagar']);
        self::assertSame(1440.0, $result['kpi']['creditoFiscal']);
        self::assertSame('Junio 2026', $result['periodLabel']);
    }

    public function testVoidedDocumentsExcluded(): void
    {
        $this->connection->insert('tax_document', [
            'tax_document_id' => 's1',
            'document_type' => '01',
            'issue_date' => '2026-06-05',
            'taxable_amount' => 10000,
            'igv_amount' => 1800,
            'void_ticket' => 'T123',
        ]);

        $query = new TaxSummaryQuery($this->connection);
        $result = $query->compute('202606');

        self::assertSame(0.0, $result['igvMensual']['debitoFiscal']['baseImponible']);
        self::assertSame(0.0, $result['igvMensual']['debitoFiscal']['igv']);
    }

    public function testVencimientoCalculation(): void
    {
        $query = new TaxSummaryQuery($this->connection);
        $result = $query->compute('202606');

        self::assertSame('2026-07-18', $result['vencimiento']);
    }

    public function testDecemberPeriodVencimiento(): void
    {
        $query = new TaxSummaryQuery($this->connection);
        $result = $query->compute('202612');

        self::assertSame('2027-01-18', $result['vencimiento']);
    }

    public function testDetraccionesPendientes(): void
    {
        $this->connection->insert('tax_document', [
            'tax_document_id' => 's1',
            'document_type' => '01',
            'issue_date' => '2026-06-05',
            'taxable_amount' => 10000,
            'igv_amount' => 1800,
            'detraction_amount' => 400,
            'detraction_deposited_at' => null,
        ]);
        $this->connection->insert('tax_document', [
            'tax_document_id' => 's2',
            'document_type' => '01',
            'issue_date' => '2026-06-10',
            'taxable_amount' => 5000,
            'igv_amount' => 900,
            'detraction_amount' => 200,
            'detraction_deposited_at' => '2026-06-12 10:00:00',
        ]);

        $query = new TaxSummaryQuery($this->connection);
        $result = $query->compute('202606');

        self::assertSame(400.0, $result['kpi']['detraccionesPendientes']);
    }
}
