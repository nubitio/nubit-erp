<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Sire;

use App\Organization\Service\CompanyContext;
use App\Reporting\Sire\RceExporter;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Ejecuta el SQL real del RCE contra el esquema de las migraciones. */
final class RceExporterIntegrationTest extends KernelTestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testBuildsTheFileFromPostedReceiptsExcludingWhatCannotBeReported(): void
    {
        $db = $this->connection();
        $db->beginTransaction();
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $db->insert('proveedor', [
            'proveedor_id' => 'sup-1',
            'ruc' => '20123456789',
            'razon_social' => 'PROVEEDOR PEÑA SAC',
        ]);
        $this->receipt($db, 'r1', [
            'type' => '01',
            'series' => 'F001',
            'number' => '00000010',
            'at' => '2026-10-05 10:00:00',
            'subtotal' => 100,
            'tax' => 18,
            'total' => 118,
        ]);
        // Compra sin IGV: va como adquisición no gravada.
        $this->receipt($db, 'r2', [
            'type' => '01',
            'series' => 'F001',
            'number' => '00000011',
            'at' => '2026-10-06 10:00:00',
            'subtotal' => 50,
            'tax' => 0,
            'total' => 50,
        ]);
        // Sin comprobante del proveedor: se excluye.
        $this->receipt($db, 'r3', [
            'type' => null,
            'series' => null,
            'number' => null,
            'at' => '2026-10-07 10:00:00',
            'subtotal' => 10,
            'tax' => 1.8,
            'total' => 11.8,
        ]);
        // Nota de crédito: se excluye (falta el comprobante que modifica).
        $this->receipt($db, 'r4', [
            'type' => '07',
            'series' => 'FC01',
            'number' => '00000001',
            'at' => '2026-10-08 10:00:00',
            'subtotal' => 10,
            'tax' => 1.8,
            'total' => 11.8,
        ]);
        // Boleta con IGV: va, con advertencia de crédito fiscal.
        $this->receipt($db, 'r5', [
            'type' => '03',
            'series' => 'B001',
            'number' => '00000002',
            'at' => '2026-10-09 10:00:00',
            'subtotal' => 20,
            'tax' => 3.6,
            'total' => 23.6,
        ]);
        // Anulada y fuera de periodo: no cuentan.
        $this->receipt($db, 'r6', [
            'type' => '01',
            'series' => 'F001',
            'number' => '00000012',
            'at' => '2026-10-10 10:00:00',
            'subtotal' => 1,
            'tax' => 0.18,
            'total' => 1.18,
            'status' => 'VOIDED',
        ]);
        $this->receipt($db, 'r7', [
            'type' => '01',
            'series' => 'F001',
            'number' => '00000013',
            'at' => '2026-11-02 10:00:00',
            'subtotal' => 1,
            'tax' => 0.18,
            'total' => 1.18,
        ]);

        $company = $this->createStub(CompanyContext::class);
        $company->method('getId')->willReturn('20601234567');
        $company->method('getName')->willReturn('EMPRESA SAC');

        $result = new RceExporter($db, $company)->export('202610');

        self::assertSame(3, $result->recordCount);
        self::assertSame(
            ['Sin comprobante del proveedor', 'Nota sin el comprobante que modifica'],
            array_column($result->excluded, 'reason'),
        );

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
        self::assertCount(3, $lines);
        $f = array_map(static fn(string $l): array => explode('|', substr($l, 0, -1)), $lines);
        foreach ($f as $fields) {
            self::assertCount(41, $fields, implode('|', $fields));
        }

        self::assertSame(['01', 'F001', '10', '6', '20123456789', 'PROVEEDOR PENA SAC'], [
            $f[0][6],
            $f[0][7],
            $f[0][9],
            $f[0][11],
            $f[0][12],
            $f[0][13],
        ]);
        self::assertSame(['100.00', '18.00', '0.00', '118.00'], [$f[0][14], $f[0][15], $f[0][20], $f[0][24]]);
        self::assertSame(['0.00', '0.00', '50.00', '50.00'], [$f[1][14], $f[1][15], $f[1][20], $f[1][24]]);
        self::assertSame('03', $f[2][6]);

        self::assertSame(
            ['B001-00000002 03: este tipo de comprobante no da crédito fiscal; revisa si el IGV debe ir con la base.'],
            array_values(array_filter($result->warnings, static fn(string $w): bool => str_starts_with($w, 'B001'))),
        );
        self::assertSame('LE2060123456720261000080400021112.zip', $result->zipName);
        self::assertNotSame('', $result->zip);
    }

    private function connection(): Connection
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $this->connection = $connection;
    }

    /**
     * @param array{type: ?string, series: ?string, number: ?string, at: string, subtotal: float|int, tax: float|int, total: float|int, status?: string} $data
     */
    private function receipt(Connection $db, string $id, array $data): void
    {
        $db->insert('goods_receipts', [
            'id' => $id,
            'branch_id' => 'b1',
            'warehouse_id' => 'w1',
            'buyer_user_id' => 1,
            'supplier_id' => 'sup-1',
            'status' => $data['status'] ?? 'POSTED',
            'issued_at' => $data['at'],
            'subtotal' => $data['subtotal'],
            'tax_total' => $data['tax'],
            'grand_total' => $data['total'],
            'supplier_doc_type' => $data['type'],
            'supplier_doc_series' => $data['series'],
            'supplier_doc_number' => $data['number'],
        ]);
    }
}
