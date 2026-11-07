<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Sire;

use App\Reporting\Sire\RceDocument;
use App\Reporting\Sire\RceRecordBuilder;
use App\Reporting\Sire\SireParty;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** La disposición de campos sigue el Anexo 11 de la RS 040-2022 (ver {@see RceRecordBuilder}). */
final class RceRecordBuilderTest extends TestCase
{
    public function testLineHas41FieldsAndTrailingPipe(): void
    {
        $line = $this->builder()->build($this->document());

        self::assertStringEndsWith('|', $line);
        self::assertCount(41, explode('|', substr($line, 0, -1)));
        self::assertCount(RceRecordBuilder::FIELDS, explode('|', substr($line, 0, -1)));
    }

    public function testInvoiceWithIgvGoesToTheDgPairAndHeaderFieldsAreFilled(): void
    {
        $f = $this->fields($this->document());

        self::assertSame(['20601234567', 'EMPRESA DE PRUEBA SAC', '202601', ''], array_slice($f, 0, 4));
        self::assertSame(['10/01/2026', '', '01', 'F001', '', '1001', ''], array_slice($f, 4, 7));
        self::assertSame(['6', '20123456789', 'PROVEEDOR SAC'], array_slice($f, 11, 3));
        // 15-20: DG base, DG IGV, DGNG base/IGV, DNG base/IGV
        self::assertSame(['100.00', '18.00', '0.00', '0.00', '0.00', '0.00'], array_slice($f, 14, 6));
        self::assertSame('0.00', $f[20], 'adquisiciones no gravadas');
        self::assertSame('118.00', $f[24]);
        self::assertSame('PEN', $f[25]);
        self::assertSame(['', '', '', ''], array_slice($f, 37, 4), 'los campos 38-41 van presentes y vacíos');
    }

    public function testPurchaseWithoutIgvGoesToNotTaxedAcquisitions(): void
    {
        $f = $this->fields($this->document(taxable: '0', igv: '0', notTaxed: '50', total: '50'));

        self::assertSame(['0.00', '0.00'], array_slice($f, 14, 2));
        self::assertSame('50.00', $f[20]);
        self::assertSame('50.00', $f[24]);
    }

    public function testCreditNoteIsNegative(): void
    {
        $f = $this->fields($this->document(type: '07'));

        self::assertSame(['-100.00', '-18.00'], array_slice($f, 14, 2));
        self::assertSame('-118.00', $f[24]);
    }

    public function testForeignCurrencyCarriesExchangeRateButSolesDoNot(): void
    {
        $usd = $this->fields($this->document(currency: 'USD', rate: '3.7512'));
        $pen = $this->fields($this->document(currency: 'PEN', rate: '3.75'));

        self::assertSame(['USD', '3.751'], array_slice($usd, 25, 2));
        self::assertSame(['PEN', ''], array_slice($pen, 25, 2));
    }

    public function testDueDateIsRequiredOnlyForCertainDocumentTypes(): void
    {
        self::assertTrue(RceRecordBuilder::requiresDueDate('14'));
        self::assertFalse(RceRecordBuilder::requiresDueDate('01'));
    }

    /** @return list<string> */
    private function fields(RceDocument $doc): array
    {
        return explode('|', substr($this->builder()->build($doc), 0, -1));
    }

    private function builder(): RceRecordBuilder
    {
        return new RceRecordBuilder('20601234567', 'EMPRESA DE PRUEBA SAC', '202601');
    }

    private function document(
        string $type = '01',
        string $taxable = '100',
        string $igv = '18',
        string $notTaxed = '0',
        string $total = '118',
        string $currency = 'PEN',
        ?string $rate = null,
    ): RceDocument {
        return new RceDocument(
            type: $type,
            series: 'F001',
            number: '00001001',
            issueDate: new DateTimeImmutable('2026-01-10'),
            supplier: new SireParty('6', '20123456789', 'PROVEEDOR SAC'),
            taxable: BigDecimal::of($taxable),
            igv: BigDecimal::of($igv),
            notTaxed: BigDecimal::of($notTaxed),
            total: BigDecimal::of($total),
            currency: $currency,
            exchangeRate: $rate,
        );
    }
}
