<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Sire;

use App\Reporting\Sire\RvieAmounts;
use App\Reporting\Sire\RvieDocument;
use App\Reporting\Sire\RvieModifiedDocument;
use App\Reporting\Sire\RvieRecordBuilder;
use App\Reporting\Sire\SireParty;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Los casos literales siguen los de `tests/test_driver_sire.py` de contaperu
 * (https://github.com/contaperu/contaperu, © 2026 Global Procesos AI S.A.C., MIT).
 */
final class RvieRecordBuilderTest extends TestCase
{
    public function testInvoiceLineIsTheExactShapeSunatAccepted(): void
    {
        $line = $this->builder()->build($this->document([
            'number' => '00001001',
            'taxable' => '25000',
            'igv' => '4500',
            'total' => '29500',
        ]));

        self::assertSame(
            '20601234567|EMPRESA DE PRUEBA SAC|202512||02/12/2025||01|F001|1001||6|20609999999|'
            . 'DISTRIBUIDORA COMERCIAL DEL NORTE SAC|0.00|25000.00|0.00|4500.00|0.00|0.00|0.00|0.00|0.00|0.00|0.00|'
            . '0.00|29500.00|PEN|||||||',
            $line,
        );
    }

    public function testCarriesExactly33InformedFieldsAndTrailingPipe(): void
    {
        $line = $this->builder()->build($this->document());

        self::assertStringEndsWith('|', $line);
        self::assertCount(33, explode('|', substr($line, 0, -1)));
    }

    public function testCreditNoteIsNegativeAndReferencesTheModifiedDocument(): void
    {
        $note = $this->document([
            'type' => '07',
            'series' => 'FC01',
            'number' => '7',
            'taxable' => '100',
            'igv' => '18',
            'total' => '118',
            'modified' => ['2025-12-02', '01', 'F001', '00001001'],
        ]);

        $f = explode('|', substr($this->builder()->build($note), 0, -1));

        self::assertSame('-100.00', $f[14]);
        self::assertSame('-18.00', $f[16]);
        self::assertSame('-118.00', $f[25]);
        self::assertSame(['02/12/2025', '01', 'F001', '1001'], array_slice($f, 28, 4));
    }

    public function testDebitNoteStaysPositive(): void
    {
        $f = explode('|', substr(
            $this->builder()->build($this->document([
                'type' => '08',
                'taxable' => '10',
                'igv' => '1.8',
                'total' => '11.8',
            ])),
            0,
            -1,
        ));

        self::assertSame('10.00', $f[14]);
        self::assertSame('1.80', $f[16]);
    }

    public function testForeignCurrencyCarriesExchangeRateButSolesDoNot(): void
    {
        $usd = explode('|', substr(
            $this->builder()->build($this->document(['currency' => 'USD', 'rate' => '3.7512'])),
            0,
            -1,
        ));
        $pen = explode('|', substr(
            $this->builder()->build($this->document(['currency' => 'PEN', 'rate' => '3.751'])),
            0,
            -1,
        ));

        self::assertSame(['USD', '3.751'], array_slice($usd, 26, 2));
        self::assertSame(['PEN', ''], array_slice($pen, 26, 2), 'en soles el tipo de cambio no se informa');
    }

    public function testNamesAreFoldedToAsciiWithoutSeparators(): void
    {
        $line = $this->builder()->build($this->document(['name' => 'PEÑA & CÍA S.A.C. | Lima/Perú']));

        self::assertStringContainsString('|PENA & CIA S.A.C. Lima-Peru|', $line);
        self::assertTrue(mb_check_encoding($line, 'ASCII'));
    }

    public function testExoneratedUnaffectedAndExportGoToTheirOwnColumns(): void
    {
        $doc = $this->document([
            'taxable' => '0',
            'igv' => '0',
            'total' => '300',
            'exempt' => '100',
            'unaffected' => '50',
            'export' => '150',
        ]);

        $f = explode('|', substr($this->builder()->build($doc), 0, -1));

        self::assertSame('150.00', $f[13]);
        self::assertSame('0.00', $f[14]);
        self::assertSame('100.00', $f[18]);
        self::assertSame('50.00', $f[19]);
        self::assertSame('300.00', $f[25]);
    }

    private function builder(): RvieRecordBuilder
    {
        return new RvieRecordBuilder('20601234567', 'EMPRESA DE PRUEBA SAC', '202512');
    }

    /**
     * @param array{
     *     type?: string, series?: string, number?: string, name?: string, currency?: string, rate?: ?string,
     *     taxable?: string, igv?: string, total?: string, exempt?: string, unaffected?: string, export?: string,
     *     modified?: ?array{0: string, 1: string, 2: string, 3: string}
     * } $overrides
     */
    private function document(array $overrides = []): RvieDocument
    {
        $modified = $overrides['modified'] ?? null;

        return new RvieDocument(
            type: $overrides['type'] ?? '01',
            series: $overrides['series'] ?? 'F001',
            number: $overrides['number'] ?? '1001',
            issueDate: new DateTimeImmutable('2025-12-02'),
            customer: new SireParty('6', '20609999999', $overrides['name'] ?? 'DISTRIBUIDORA COMERCIAL DEL NORTE SAC'),
            amounts: RvieAmounts::of(
                total: $overrides['total'] ?? '118',
                taxable: $overrides['taxable'] ?? '100',
                igv: $overrides['igv'] ?? '18',
                exempt: $overrides['exempt'] ?? '0',
                unaffected: $overrides['unaffected'] ?? '0',
                export: $overrides['export'] ?? '0',
            ),
            currency: $overrides['currency'] ?? 'PEN',
            exchangeRate: $overrides['rate'] ?? null,
            modified: $modified !== null
                ? new RvieModifiedDocument(
                    new DateTimeImmutable($modified[0]),
                    $modified[1],
                    $modified[2],
                    $modified[3],
                )
                : null,
        );
    }
}
