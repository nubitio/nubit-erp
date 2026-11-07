<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Sire;

use App\Organization\Service\CompanyContext;
use App\Reporting\Sire\RvieExporter;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use ZipArchive;

/** Ejecuta el SQL real contra el esquema de las migraciones (periodo sin comprobantes). */
final class RvieExporterIntegrationTest extends KernelTestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testBuildsTheFileFromStoredDocumentsClassifyingAmountsAndFlaggingIssues(): void
    {
        $db = $this->connection();
        $db->beginTransaction();

        $db->insert('cliente', ['id' => 9_999_001, 'ruc' => '20609999999', 'razon_social' => 'CLIENTE PEÑA SAC']);

        $this->document($db, 'inv-1', '01', 'F001', '00000001', '2026-10-05', 100, 18, 118, 9_999_001);
        $this->line($db, 'inv-1', '10', 100);
        // Factura exonerada: el emisor suma todas las líneas en taxable_amount, el IGV es cero.
        $this->document($db, 'inv-2', '01', 'F001', '00000002', '2026-10-06', 50, 0, 50, 9_999_001);
        $this->line($db, 'inv-2', '20', 50);
        $db->insert('tax_document', [
            'tax_document_id' => 'nc-1',
            'document_type' => '07',
            'series' => 'FC01',
            'number' => '00000003',
            'issue_date' => '2026-10-07',
            'taxable_amount' => 50,
            'igv_amount' => 9,
            'total' => 59,
            'customer_id' => 9_999_001,
            'related_tax_document_id' => 'inv-1',
        ]);
        $this->line($db, 'nc-1', '10', 50);
        // Boleta dada de baja: no va en el archivo.
        $this->document($db, 'bol-void', '03', 'B001', '00000004', '2026-10-08', 10, 1.8, 11.8, null);
        $db->update('tax_document', ['void_ticket' => 'T123'], ['tax_document_id' => 'bol-void']);
        // Boleta sin líneas ni cliente: se informa gravada y con advertencia.
        $this->document($db, 'bol-1', '03', 'B001', '00000005', '2026-10-09', 20, 3.6, 23.6, null);
        // Fuera del periodo.
        $this->document($db, 'inv-out', '01', 'F001', '00000099', '2026-11-02', 1, 0.18, 1.18, 9_999_001);

        $company = $this->createStub(CompanyContext::class);
        $company->method('getId')->willReturn('20601234567');
        $company->method('getName')->willReturn('EMPRESA SAC');

        $result = new RvieExporter($db, $company)->export('202610');

        self::assertSame(4, $result['recordCount']);
        self::assertSame([['document' => 'B001-00000004 03', 'reason' => 'Dado de baja']], $result['excluded']);

        $lines = explode("\r\n", rtrim($result['content'], "\r\n"));
        self::assertCount(4, $lines);
        $f = array_map(static fn(string $l): array => explode('|', substr($l, 0, -1)), $lines);
        foreach ($f as $fields) {
            self::assertCount(33, $fields, implode('|', $fields));
        }

        // 1: factura gravada
        self::assertSame(['01', 'F001', '1', '6', '20609999999', 'CLIENTE PENA SAC'], [
            $f[0][6],
            $f[0][7],
            $f[0][8],
            $f[0][10],
            $f[0][11],
            $f[0][12],
        ]);
        self::assertSame(['100.00', '18.00', '0.00', '118.00'], [$f[0][14], $f[0][16], $f[0][18], $f[0][25]]);
        // 2: factura exonerada va en la columna de exonerado, no como gravada
        self::assertSame(['0.00', '0.00', '50.00', '50.00'], [$f[1][14], $f[1][16], $f[1][18], $f[1][25]]);
        // 3: nota de crédito en negativo y con su referencia
        self::assertSame(['-50.00', '-9.00', '-59.00'], [$f[2][14], $f[2][16], $f[2][25]]);
        self::assertSame(['05/10/2026', '01', 'F001', '1'], array_slice($f[2], 28, 4));
        // 4: boleta sin cliente
        self::assertSame(['03', 'B001', '5', '0', '00000000', 'VARIOS'], [
            $f[3][6],
            $f[3][7],
            $f[3][8],
            $f[3][10],
            $f[3][11],
            $f[3][12],
        ]);
        self::assertSame('20.00', $f[3][14]);

        self::assertSame(['B001-00000005 03: sin líneas: la base se informó como gravada.'], $result['warnings']);

        $zipPath = tempnam(sys_get_temp_dir(), 'rvie_test_');
        self::assertIsString($zipPath);
        file_put_contents($zipPath, $result['zip']);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));
        self::assertSame($result['txtName'], $zip->getNameIndex(0));
        self::assertSame($result['content'], $zip->getFromIndex(0));
        $zip->close();
        unlink($zipPath);
        self::assertSame('LE2060123456720261000140400021112.zip', $result['zipName']);
    }

    private function connection(): Connection
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $this->connection = $connection;
    }

    private function document(
        Connection $db,
        string $id,
        string $type,
        string $series,
        string $number,
        string $date,
        float|int $taxable,
        float|int $igv,
        float|int $total,
        ?int $customer,
    ): void {
        $db->insert('tax_document', [
            'tax_document_id' => $id,
            'document_type' => $type,
            'series' => $series,
            'number' => $number,
            'issue_date' => $date,
            'taxable_amount' => $taxable,
            'igv_amount' => $igv,
            'total' => $total,
            'customer_id' => $customer,
        ]);
    }

    private function line(Connection $db, string $documentId, string $taxType, float|int $taxed): void
    {
        $db->insert('tax_document_line', [
            'tax_document_id' => $documentId,
            'line_number' => 1,
            'product_name' => 'Producto',
            'quantity' => 1,
            'unit_price' => $taxed,
            'tax_type' => $taxType,
            'taxed_amount' => $taxed,
            'total' => $taxed,
            'created_at' => '2026-10-01 00:00:00',
        ]);
    }

    public function testQueryRunsAgainstTheMappedSchema(): void
    {
        self::bootKernel();
        $company = $this->createStub(CompanyContext::class);
        $company->method('getId')->willReturn('20601234567');
        $company->method('getName')->willReturn('EMPRESA DE PRUEBA SAC');

        $result = new RvieExporter($this->connection(), $company)->export('199001');

        self::assertSame(0, $result['recordCount']);
        self::assertSame('', $result['content']);
        self::assertSame('', $result['zip']);
        self::assertSame([], $result['excluded']);
        self::assertStringEndsWith('2060123456719900100140400021112.zip', $result['zipName']);
    }
}
