<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Service\InvoiceCalculator;
use App\Billing\Service\InvoiceCreationService;
use App\Billing\Service\InvoiceLineInput;
use App\Sales\Entity\SaleRecord;
use App\Sales\Entity\SaleItem;
use App\Sales\Service\SaleCurrencyResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SLICE-016: grand_total must NOT be overwritten with the
 * IGV-rounded total after invoice emission.
 *
 * Background: InvoiceCreationService::createForSale() calls setTotal() to store
 * the SUNAT-document total on the Sale entity.  Before the fix, this mutated
 * $sale->getTotal() BEFORE syncInvoicedSaleState() read it, causing grand_total
 * in the sales table to receive the IGV-recalculated value instead of the
 * original customer-facing total.
 */
final class InvoiceCreationServiceGrandTotalTest extends TestCase
{
    // ------------------------------------------------------------------ //
    // Part 1 – InvoiceCalculator divergence documentation                  //
    // ------------------------------------------------------------------ //

    /**
     * Documents that InvoiceCalculator can return a total that intentionally
     * differs from the original sale total by exactly 1 cent when IGV rounding
     * is applied across multiple lines.
     *
     * Scenario: 2 items at 1.18 each.
     *   Pure line-level calculation produces 2.36 (2 × 1.18).
     *   When the customer was charged 2.37 (original sale total), the calculator
     *   applies a +0.01 adjustment on the last line so the SUNAT document totals
     *   to 2.37 — matching the amount collected.
     *
     * The IMPORTANT invariant is that grand_total in the sales table must always
     * hold the ORIGINAL total (2.37 here), not the pure IGV-recalculated figure.
     */
    public function testInvoiceCalculatorTotalMayDivergeFromOriginalSaleTotal(): void
    {
        $lines = [
            new InvoiceLineInput('1', '1.18'),
            new InvoiceLineInput('1', '1.18'),
        ];
        $originalTotal = '2.37';

        $calculator = new InvoiceCalculator($lines, '0', '18', '0', $originalTotal);
        $amounts = $calculator->calculate();

        // The calculator's document total equals the expectedTotal (it adjusts
        // the last line by +0.01 to match).
        self::assertSame($originalTotal, $amounts->getTotal());

        // However, a naive pure-IGV recalculation WITHOUT the expectedTotal hint
        // would produce a different value — demonstrating that the IGV-rounded
        // total is NOT always equal to the original sale total.
        $pureCalculator = new InvoiceCalculator($lines, '0', '18', '0', null);
        $pureAmounts = $pureCalculator->calculate();

        self::assertSame('2.36', $pureAmounts->getTotal());
        self::assertNotSame($originalTotal, $pureAmounts->getTotal());
    }

    /**
     * Documents the same divergence for a 4-line sale mirroring the SLICE-016
     * bug scenario: items at 1.18, 1.18, 1.18, 1.18 with original total 4.73.
     * Pure IGV recalculation yields 4.72; the original total is 4.73.
     */
    public function testInvoiceCalculatorFourLinesDivergence(): void
    {
        $lines = [
            new InvoiceLineInput('1', '1.18'),
            new InvoiceLineInput('1', '1.18'),
            new InvoiceLineInput('1', '1.18'),
            new InvoiceLineInput('1', '1.18'),
        ];
        $originalTotal = '4.73';

        // Pure IGV calculation (no expectedTotal hint).
        $pureCalculator = new InvoiceCalculator($lines, '0', '18', '0', null);
        $pureAmounts = $pureCalculator->calculate();

        // The pure recalculation differs from the original total by 1 cent.
        self::assertSame('4.72', $pureAmounts->getTotal());
        self::assertNotSame($originalTotal, $pureAmounts->getTotal());

        // With the expectedTotal hint the calculator adjusts and matches the
        // original total; this is what InvoiceCreationService passes so that
        // the SUNAT document total equals the amount the customer paid.
        $adjustedCalculator = new InvoiceCalculator($lines, '0', '18', '0', $originalTotal);
        $adjustedAmounts = $adjustedCalculator->calculate();

        self::assertSame($originalTotal, $adjustedAmounts->getTotal());
    }

    // ------------------------------------------------------------------ //
    // Part 2 – InvoiceCreationService preserves original grand_total       //
    // ------------------------------------------------------------------ //

    /**
     * Verifies that after invoice emission the value written to grand_total in
     * the sales table is the ORIGINAL sale total — not the IGV-recalculated
     * document total that may differ by 1 cent.
     *
     * Regression for SLICE-016: the old code captured $sale->getTotal() AFTER
     * $sale->setTotal($invoiceAmounts->getTotal()) had already mutated the value.
     * The fix captures $originalTotal BEFORE setTotal() is called.
     */
    public function testGrandTotalIsPreservedAfterInvoiceEmission(): void
    {
        $originalTotal = '4.73';
        $sale = $this->buildSaleWithFourItems($originalTotal);

        $grandTotalWritten = null;

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturnCallback(
                static function (string $sql, array $params = []) {
                    // local_id for the warehouse lookup
                    if (str_contains($sql, 'almacen')) {
                        return 'local-1';
                    }

                    // Existing invoice check — return false so we proceed
                    if (str_contains($sql, 'tax_document_id') && str_contains($sql, 'FOR UPDATE')) {
                        return false;
                    }

                    // Series lookup from local_comprobante
                    if (str_contains($sql, 'local_comprobante')) {
                        return 'F001';
                    }

                    // Next invoice number
                    if (str_contains($sql, 'IFNULL') || str_contains($sql, 'MAX(')) {
                        return '1';
                    }

                    // UUID generation
                    if (str_contains($sql, 'UUID')) {
                        return 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
                    }

                    // V2 sale_id lookup in sales table
                    if (str_contains($sql, 'legacy_movimiento_id')) {
                        return 'v2-sale-id-1';
                    }

                    // information_schema table-existence check:
                    // allow 'sales', block 'movimiento' so only one executeStatement fires.
                    if (str_contains($sql, 'information_schema')) {
                        return isset($params['table_name']) && $params['table_name'] === 'sales' ? 1 : false;
                    }

                    return false;
                }
            );

        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('insert');

        // Capture the grand_total value written to the sales table.
        $connection->expects(self::atLeastOnce())
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params = []) use (&$grandTotalWritten): int {
                    if (str_contains($sql, 'grand_total')) {
                        $grandTotalWritten = $params['total'] ?? null;
                    }

                    return 1;
                }
            );

        $resolver = $this->createStub(SaleCurrencyResolver::class);
        $resolver->method('resolveForSaleId')
            ->willReturn(['currency' => 'PEN', 'exchange_rate' => '1.00']);

        $service = new InvoiceCreationService($connection, $resolver);
        $result = $service->createForSale($sale);

        self::assertTrue($result['success'], 'Invoice creation must succeed: ' . $result['message']);

        // The grand_total written to the DB must be the ORIGINAL sale total, not
        // the IGV-recalculated document total that may be 1 cent lower.
        self::assertNotNull($grandTotalWritten, 'grand_total must have been written to the sales table');
        self::assertSame(
            $originalTotal,
            $grandTotalWritten,
            'grand_total must equal the original sale total, not the IGV-recalculated document total'
        );
    }

    // ------------------------------------------------------------------ //
    // Helpers                                                              //
    // ------------------------------------------------------------------ //

    /**
     * Builds a Sale with 4 SaleItem lines at 1.18 each (total 4.73 — which
     * differs by 1 cent from the pure IGV-recalculated 4.72).
     */
    private function buildSaleWithFourItems(string $total): SaleRecord
    {
        $now = new \DateTimeImmutable('now');
        $sale = (new SaleRecord())
            ->setId('sale-igv-test-1')
            ->setFecha($now)
            ->setDateCreated($now)
            ->setIgv('18')
            ->setDescuento('0')
            ->setTotal($total);

        foreach (['1.18', '1.18', '1.18', '1.18'] as $price) {
            $item = new SaleItem();
            // SaleItem uses float for cantidad; string for precio_unitario.
            $refItem = new \ReflectionObject($item);

            $cantidadProp = $refItem->getProperty('cantidad');
            $cantidadProp->setValue($item, 1.0);

            $precioProp = $refItem->getProperty('precio_unitario');
            $precioProp->setValue($item, $price);

            $sale->addItem($item);
        }

        return $sale;
    }
}
