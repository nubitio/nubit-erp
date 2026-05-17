<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use Nubit\Platform\Exception\ServiceException;
use App\Contacts\Entity\Customer;
use App\Catalog\Entity\Product;
use App\Sales\Entity\SaleRecord;
use App\Sales\Entity\SaleItemRecord;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Sales\State\DetractionCalculator;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DetractionCalculatorTest extends TestCase
{
    private SunatCatalogRepository&MockObject $sunatCatalogRepository;
    private LoggerInterface&MockObject $logger;
    private DetractionCalculator $calculator;

    protected function setUp(): void
    {
        $this->sunatCatalogRepository = $this->createMock(SunatCatalogRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->calculator = new DetractionCalculator($this->sunatCatalogRepository, $this->logger);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCustomer(string $ruc): Customer&MockObject
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getRuc')->willReturn($ruc);

        return $customer;
    }

    private function makeDetractionCatalog(int $id, string $code, float $percentage): SunatCatalog&MockObject
    {
        $catalog = $this->createMock(SunatCatalog::class);
        $catalog->method('getId')->willReturn($id);
        $catalog->method('getCode')->willReturn($code);
        $catalog->method('getPercentage')->willReturn($percentage);

        return $catalog;
    }

    /**
     * @param list<SaleItemRecord> $items
     */
    private function makeSale(?Customer $customer, string $total, array $items = [], string $currency = 'PEN', string $exchangeRate = '1.000000'): SaleRecord&MockObject
    {
        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn($total);
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection($items));
        $sale->method('getCurrency')->willReturn($currency);
        $sale->method('getExchangeRate')->willReturn($exchangeRate);

        return $sale;
    }

    private function makeSaleItemWithCatalog(?SunatCatalog $catalog): SaleItemRecord&MockObject
    {
        $product = $this->createStub(Product::class);
        $product->method('getDetractionCatalog')->willReturn($catalog);

        $item = $this->createMock(SaleItemRecord::class);
        $item->method('getProduct')->willReturn($product);

        return $item;
    }

    private function makePaymentMethod(): SunatCatalog&MockObject
    {
        return $this->createMock(SunatCatalog::class);
    }

    // -------------------------------------------------------------------------
    // Customer without 11-digit RUC → detraction cleared
    // -------------------------------------------------------------------------

    public function testNonRucCustomerClearsDetraction(): void
    {
        $customer = $this->makeCustomer('12345678'); // 8 digits, not RUC
        $sale = $this->makeSale($customer, '1000.00');

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(null);
        $sale->expects(self::once())->method('setDetraccionMonto')->with(null);
        $sale->expects(self::once())->method('setDetraccionMedioPago')->with(null);
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Null customer (no client linked) → detraction cleared
    // -------------------------------------------------------------------------

    public function testNullCustomerClearsDetraction(): void
    {
        $sale = $this->makeSale(null, '1000.00');

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // No items with detraction catalog → detraction cleared
    // -------------------------------------------------------------------------

    public function testNoDetractionCatalogOnItemsClearsDetraction(): void
    {
        $customer = $this->makeCustomer('20123456789'); // valid 11-digit RUC
        $item = $this->makeSaleItemWithCatalog(null); // no catalog
        $sale = $this->makeSale($customer, '2000.00', [$item]);

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Empty items array → no exception, detraction cleared
    // -------------------------------------------------------------------------

    public function testZeroItemSaleDoesNotThrow(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $sale = $this->makeSale($customer, '0.00', []);

        // Should reach clearDetraction without error
        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Sale below threshold (standard code, 700 threshold) → detraction cleared
    // -------------------------------------------------------------------------

    public function testSaleBelowStandardThresholdClearsDetraction(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0); // standard code, threshold = 700
        $item = $this->makeSaleItemWithCatalog($catalog);
        $sale = $this->makeSale($customer, '500.00', [$item]); // below 700

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Sale exactly at threshold → clearDetraction (threshold is exclusive: total <= threshold)
    // -------------------------------------------------------------------------

    public function testSaleExactlyAtThresholdClearsDetraction(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);
        $item = $this->makeSaleItemWithCatalog($catalog);
        $sale = $this->makeSale($customer, '700.00', [$item]); // exactly 700 → cleared

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Sale above threshold, valid detraction code → amount calculated and set
    // -------------------------------------------------------------------------

    public function testSaleAboveThresholdAppliesDetraction(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0); // 4%
        $item = $this->makeSaleItemWithCatalog($catalog);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('1000.00');
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$item]));

        // After setting code and percentage, getDetraccionPorcentaje returns 4.0 and getTotal returns '1000.00'
        $sale->method('getDetraccionPorcentaje')->willReturn(4.0);

        $sale->expects(self::once())->method('setDetraccionCodigo')->with('037');
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(4.0);
        $sale->expects(self::once())->method('setDetraccionMonto')->with(40.0); // 1000 * 4 / 100 = 40
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(1000.0);

        $paymentMethod = $this->makePaymentMethod();
        $this->sunatCatalogRepository->method('findOneBy')->willReturn($paymentMethod);

        $sale->method('getTipoOperacion')->willReturn(null);
        $sale->expects(self::once())->method('setTipoOperacion')->with('1001');
        $sale->expects(self::once())->method('setDetraccionMedioPago')->with(DetractionCalculator::PAYMENT_METHOD);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // NO_MIN_CODES code (no threshold) → detraction applies even on small amounts
    // -------------------------------------------------------------------------

    public function testNoMinCodeAppliesDetractionBelowStandardThreshold(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '008', 10.0); // '008' has no minimum
        $item = $this->makeSaleItemWithCatalog($catalog);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('200.00'); // below 700 but no threshold applies
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$item]));
        $sale->method('getDetraccionPorcentaje')->willReturn(10.0);

        $sale->expects(self::once())->method('setDetraccionCodigo')->with('008');
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(10.0);
        $sale->expects(self::once())->method('setDetraccionMonto')->with(20.0); // 200 * 10 / 100
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(200.0);

        $paymentMethod = $this->makePaymentMethod();
        $this->sunatCatalogRepository->method('findOneBy')->willReturn($paymentMethod);

        $sale->method('getTipoOperacion')->willReturn(null);
        $sale->expects(self::once())->method('setTipoOperacion')->with('1001');

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Two items with DIFFERENT detraction catalogs → throws ServiceException
    // -------------------------------------------------------------------------

    public function testMixedDetractionCodesThrowsServiceException(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalogA = $this->makeDetractionCatalog(1, '037', 4.0);
        $catalogB = $this->makeDetractionCatalog(2, '012', 6.0); // different id

        $itemA = $this->makeSaleItemWithCatalog($catalogA);
        $itemB = $this->makeSaleItemWithCatalog($catalogB);

        $sale = $this->makeSale($customer, '2000.00', [$itemA, $itemB]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/diferentes códigos de detracción/');

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Two items with SAME detraction catalog → no exception, applies once
    // -------------------------------------------------------------------------

    public function testSameDetractionCodeAcrossMultipleItemsAppliesOnce(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);

        $itemA = $this->makeSaleItemWithCatalog($catalog);
        $itemB = $this->makeSaleItemWithCatalog($catalog);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('2000.00');
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$itemA, $itemB]));
        $sale->method('getDetraccionPorcentaje')->willReturn(4.0);

        $sale->expects(self::once())->method('setDetraccionCodigo')->with('037');
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(4.0);
        $sale->expects(self::once())->method('setDetraccionMonto')->with(80.0); // 2000 * 4 / 100
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(2000.0);

        $paymentMethod = $this->makePaymentMethod();
        $this->sunatCatalogRepository->method('findOneBy')->willReturn($paymentMethod);

        $sale->method('getTipoOperacion')->willReturn(null);
        $sale->expects(self::once())->method('setTipoOperacion')->with('1001');

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // Payment method catalog not found → throws ServiceException
    // -------------------------------------------------------------------------

    public function testMissingPaymentMethodCatalogThrowsServiceException(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);
        $item = $this->makeSaleItemWithCatalog($catalog);

        $sale = $this->createStub(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('1500.00');
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$item]));
        $sale->method('getDetraccionPorcentaje')->willReturn(4.0);

        // Payment method not found
        $this->sunatCatalogRepository->method('findOneBy')->willReturn(null);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/medio de pago para detracción/');

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // tipoOperacion already set → should NOT override with '1001'
    // -------------------------------------------------------------------------

    public function testExistingTipoOperacionIsNotOverwritten(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);
        $item = $this->makeSaleItemWithCatalog($catalog);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('1000.00');
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$item]));
        $sale->method('getDetraccionPorcentaje')->willReturn(4.0);
        $sale->method('getTipoOperacion')->willReturn('9999'); // already set

        $paymentMethod = $this->makePaymentMethod();
        $this->sunatCatalogRepository->method('findOneBy')->willReturn($paymentMethod);

        // setTipoOperacion must NOT be called when already set
        $sale->expects(self::never())->method('setTipoOperacion');
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(1000.0);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // clearDetraction resets tipoOperacion only when it is '1001'
    // -------------------------------------------------------------------------

    public function testClearDetractionResetsTipoOperacionWhenItIs1001(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $sale = $this->makeSale($customer, '0.00', []); // no items → clearDetraction

        $sale->method('getTipoOperacion')->willReturn('1001');
        $sale->expects(self::once())->method('setTipoOperacion')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    public function testClearDetractionDoesNotResetTipoOperacionWhenNotDetraction(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $sale = $this->makeSale($customer, '0.00', []);

        $sale->method('getTipoOperacion')->willReturn('2000'); // unrelated code
        $sale->expects(self::never())->method('setTipoOperacion');

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // getThreshold — public method unit checks
    // -------------------------------------------------------------------------

    public function testGetThresholdReturnsZeroForNoMinimumCodes(): void
    {
        foreach (DetractionCalculator::NO_MIN_CODES as $code) {
            self::assertSame(0.0, $this->calculator->getThreshold($code), "Code $code should have 0 threshold");
        }
    }

    public function testGetThresholdReturnsDefaultForOtherCodes(): void
    {
        self::assertSame(DetractionCalculator::MIN_TOTAL, $this->calculator->getThreshold('037'));
        self::assertSame(DetractionCalculator::MIN_TOTAL, $this->calculator->getThreshold('999'));
        self::assertSame(DetractionCalculator::MIN_TOTAL, $this->calculator->getThreshold('001'));
    }

    // -------------------------------------------------------------------------
    // Null percentage on catalog → setDetraccionMonto(null)
    // -------------------------------------------------------------------------

    public function testNullPercentageOnCatalogSetsNullAmount(): void
    {
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 0.0);

        // Override to return null percentage
        $catalogNull = $this->createStub(SunatCatalog::class);
        $catalogNull->method('getId')->willReturn(1);
        $catalogNull->method('getCode')->willReturn('037');
        $catalogNull->method('getPercentage')->willReturn(null);

        $item = $this->makeSaleItemWithCatalog($catalogNull);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('1000.00');
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$item]));
        $sale->method('getDetraccionPorcentaje')->willReturn(null); // null percentage
        $sale->method('getCurrency')->willReturn('PEN');
        $sale->method('getExchangeRate')->willReturn('1.000000');

        $sale->expects(self::once())->method('setDetraccionMonto')->with(null);
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(1000.0);

        $paymentMethod = $this->makePaymentMethod();
        $this->sunatCatalogRepository->method('findOneBy')->willReturn($paymentMethod);

        $sale->method('getTipoOperacion')->willReturn(null);

        $this->calculator->applyFromItems($sale);
    }

    // -------------------------------------------------------------------------
    // USD invoice threshold: PEN-converted total must be used for comparison
    // -------------------------------------------------------------------------

    public function testUsdSaleBelowThresholdInPenClearsDetraction(): void
    {
        // USD 170 * 3.89 = 661.30 PEN → below 700 PEN threshold → cleared
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);
        $item = $this->makeSaleItemWithCatalog($catalog);
        $sale = $this->makeSale($customer, '170.00', [$item], 'USD', '3.890000');

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(null);

        $this->calculator->applyFromItems($sale);
    }

    public function testUsdSaleAboveThresholdInPenAppliesDetraction(): void
    {
        // USD 200 * 3.89 = 778.00 PEN → above 700 PEN threshold → detraction applies
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);
        $item = $this->makeSaleItemWithCatalog($catalog);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getCliente')->willReturn($customer);
        $sale->method('getTotal')->willReturn('200.00');
        $sale->method('getSaleItemRecords')->willReturn(new ArrayCollection([$item]));
        $sale->method('getCurrency')->willReturn('USD');
        $sale->method('getExchangeRate')->willReturn('3.890000');
        $sale->method('getDetraccionPorcentaje')->willReturn(4.0);

        $sale->expects(self::once())->method('setDetraccionCodigo')->with('037');
        $sale->expects(self::once())->method('setDetraccionPorcentaje')->with(4.0);
        // Amount stored in USD: 200 * 4/100 = 8.00 (native currency, NOT converted here)
        $sale->expects(self::once())->method('setDetraccionMonto')->with(8.0);
        $sale->expects(self::once())->method('setDetraccionValorReferencial')->with(200.0);

        $paymentMethod = $this->makePaymentMethod();
        $this->sunatCatalogRepository->method('findOneBy')->willReturn($paymentMethod);

        $sale->method('getTipoOperacion')->willReturn(null);
        $sale->expects(self::once())->method('setTipoOperacion')->with('1001');

        $this->calculator->applyFromItems($sale);
    }

    public function testUsdSaleExactlyAtPenThresholdClearsDetraction(): void
    {
        // USD 179.94 * 3.89 = 699.97 PEN → below 700 → cleared
        $customer = $this->makeCustomer('20123456789');
        $catalog = $this->makeDetractionCatalog(1, '037', 4.0);
        $item = $this->makeSaleItemWithCatalog($catalog);
        $sale = $this->makeSale($customer, '179.94', [$item], 'USD', '3.890000');

        $sale->expects(self::once())->method('setDetraccionCodigo')->with(null);

        $this->calculator->applyFromItems($sale);
    }
}
