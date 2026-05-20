<?php

declare(strict_types=1);

namespace App\Tests\Billing\Billing\Domain;

use Nubit\Platform\Exception\ServiceException;
use App\Billing\Sunat\Domain\BillCreateUseCase;
use App\Billing\Sunat\Domain\BillUtilsInterface;
use App\Billing\Invoice\Dto\HydratedVoucher;
use App\Billing\Invoice\Dto\HydratedVoucherItem;
use App\Billing\Invoice\Service\VoucherHydratorInterface;
use App\Billing\Shared\Enum\OperationType;
use App\Billing\Shared\Service\AmountFormatterService;
use App\Organization\Entity\Company;
use App\Organization\Repository\CompanyRepository;
use App\Billing\Repository\TaxDocumentRepository;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company as GreenterCompany;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class BillCreateUseCaseTest extends TestCase
{
    private TaxDocumentRepository&MockObject $taxDocumentRepository;
    private BillUtilsInterface&MockObject $billUtils;
    private CompanyRepository&MockObject $companyRepository;
    private VoucherHydratorInterface&MockObject $voucherHydrator;
    private AmountFormatterService $amountFormatterService;
    private Company $company;
    private BillCreateUseCase $useCase;

    protected function setUp(): void
    {
        $this->taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $this->billUtils              = $this->createMock(BillUtilsInterface::class);
        $this->companyRepository      = $this->createMock(CompanyRepository::class);
        $this->voucherHydrator        = $this->createMock(VoucherHydratorInterface::class);
        $this->amountFormatterService = new AmountFormatterService();

        $this->company = new Company();

        $this->billUtils->method('getCompany')->willReturn(new GreenterCompany());
        $this->companyRepository->method('findOneBy')->willReturn($this->company);

        $this->useCase = new BillCreateUseCase(
            $this->taxDocumentRepository,
            $this->billUtils,
            $this->companyRepository,
            $this->voucherHydrator,
            $this->amountFormatterService,
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeAddress(): Address
    {
        return new Address();
    }

    private function makeBaseVoucher(
        int $paymentMethod = 1,
        float $discount = 0.0,
        float $surcharge = 0.0,
        ?string $detractionCode = null,
        ?float $detractionPercent = null,
        ?float $detractionAmount = null,
        string $operationType = '',
        ?HydratedVoucherItem $item = null,
    ): HydratedVoucher {
        return new HydratedVoucher(
            saleId: 'sale-1',
            customerDocType: '6',
            customerDocNumber: '20100000001',
            customerName: 'Test Customer SAC',
            customerAddress: $this->makeAddress(),
            customerPhone: null,
            customerEmail: null,
            companyId: '20100000000',
            companyName: 'Test Company SAC',
            companyAddress: null,
            companyPhone: null,
            branchAddress: null,
            branchPhone: null,
            voucherType: '01',
            voucherSeries: 'F001',
            voucherNumber: '1',
            voucherPaymentMethod: $paymentMethod,
            voucherDate: new \DateTimeImmutable('2025-01-15'),
            voucherCurrency: 'PEN',
            voucherExchangeRate: null,
            voucherDiscountAmount: $discount,
            voucherDiscountPercentage: null,
            voucherDiscountTotal: $discount,
            voucherConsumptionSurchargeAmount: $surcharge,
            voucherConsumptionSurchargePercentage: null,
            voucherTaxedAmount: 84.75,
            voucherTaxAmount: 15.25,
            voucherTaxPercentage: 18.0,
            voucherTotalTaxes: 15.25,
            voucherSaleValue: 84.75,
            voucherSaleAmount: 100.00,
            voucherOperationType: $operationType,
            voucherDetractionCode: $detractionCode,
            voucherDetractionPercent: $detractionPercent,
            voucherDetractionAmount: $detractionAmount,
            voucherDetractionPaymentMethod: null,
            voucherDetractionPaymentDescription: null,
            voucherDetractionValueRef: null,
            voucherDetractionAccount: null,
            voucherDetractionDescription: null,
            voucherBankName: null,
            voucherBankAccount: null,
            voucherBankCci: null,
            voucherNote: null,
            voucherHash: null,
            voucherQrData: null,
            voucherStatus: null,
            voucherItems: $item !== null ? [$item] : [],
        );
    }

    private function makeItem(): HydratedVoucherItem
    {
        return new HydratedVoucherItem(
            productCode: 'PROD-001',
            productUom: 'NIU',
            quantity: 2.0,
            productName: 'Test Product',
            unitValue: 42.37,
            taxedAmount: 84.75,
            taxPercentage: 18.0,
            taxAmount: 15.25,
            taxType: '10',
            unitPrice: 50.0,
        );
    }

    private function setupTaxDocument(string $id = 'td-1'): void
    {
        $taxDocument = $this->createStub(\App\Billing\Entity\TaxDocument::class);
        $taxDocument->method('getId')->willReturn($id);
        $this->taxDocumentRepository->method('find')->willReturn($taxDocument);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testInvokeThrowsServiceExceptionWhenTaxDocumentNotFound(): void
    {
        $this->taxDocumentRepository->method('find')->willReturn(null);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Invoice not found');

        $this->useCase->__invoke('missing-td');
    }

    public function testInvokeReturnsInvoiceWithBasicFields(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(item: $this->makeItem());
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertSame('F001', $invoice->getSerie());
        self::assertSame('1', $invoice->getCorrelativo());
        self::assertSame('01', $invoice->getTipoDoc());
        self::assertSame('2.1', $invoice->getUblVersion());
        self::assertSame('PEN', $invoice->getTipoMoneda());
        self::assertSame(OperationType::SALE->value, $invoice->getTipoOperacion());
    }

    public function testInvokeUsesFormaPagoContadoWhenPaymentMethodIsOne(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(paymentMethod: 1);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertInstanceOf(FormaPagoContado::class, $invoice->getFormaPago());
    }

    public function testInvokeUsesFormaPagoCreditoWhenPaymentMethodIsTwo(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(paymentMethod: 2);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertInstanceOf(FormaPagoCredito::class, $invoice->getFormaPago());
        self::assertNotEmpty($invoice->getCuotas());
    }

    public function testInvokeAddsDetractionAndSetsDetraccionSaleOperationType(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(
            detractionCode: '022',
            detractionPercent: 12.0,
            detractionAmount: 12.0,
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertSame(OperationType::DETRACTION_SALE->value, $invoice->getTipoOperacion());
        self::assertNotNull($invoice->getDetraccion());
        // Two legends: amount-in-words + detraction
        self::assertCount(2, $invoice->getLegends());
    }

    public function testInvokeKeepsExplicitOperationTypeWhenDetractionPresent(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(
            detractionCode: '022',
            detractionPercent: 12.0,
            detractionAmount: 12.0,
            operationType: OperationType::SALE->value,
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        // Explicit operationType set → should NOT be overridden to DETRACTION_SALE
        self::assertSame(OperationType::SALE->value, $invoice->getTipoOperacion());
    }

    public function testInvokeCalculatesDetractionAmountFromPercentWhenAmountIsNull(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(
            detractionCode: '022',
            detractionPercent: 10.0,
            detractionAmount: null,
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $detraction = $invoice->getDetraccion();
        self::assertNotNull($detraction);
        // 10% of 100.00 = 10.00
        self::assertEqualsWithDelta(10.0, $detraction->getMount(), 0.01);
    }

    public function testInvokeAddsSurchargeCargoWhenSurchargeIsPositive(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(surcharge: 5.0);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertNotEmpty($invoice->getCargos());
    }

    public function testInvokeAddsDiscountDescuentoWhenDiscountIsPositive(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(discount: 10.0);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertNotEmpty($invoice->getDescuentos());
    }

    public function testInvokeMapsSaleItemsToDetails(): void
    {
        $this->setupTaxDocument();
        $item    = $this->makeItem();
        $voucher = $this->makeBaseVoucher(item: $item);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertCount(1, $invoice->getDetails());
        $detail = $invoice->getDetails()[0];
        self::assertSame('PROD-001', $detail->getCodProducto());
        self::assertSame('Test Product', $detail->getDescripcion());
    }

    public function testInvokeHasOneLegendWhenNoDetraction(): void
    {
        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher();
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertCount(1, $invoice->getLegends());
    }

    public function testInvokeUsesDetractionAccountFromCompanyWhenSet(): void
    {
        $this->company->setDetraccionAccount('00-000-123456');

        $this->setupTaxDocument();
        $voucher = $this->makeBaseVoucher(
            detractionCode: '022',
            detractionPercent: 12.0,
            detractionAmount: 12.0,
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $detraction = $invoice->getDetraccion();
        self::assertNotNull($detraction);
        self::assertSame('00-000-123456', $detraction->getCtaBanco());
    }

    // -----------------------------------------------------------------------
    // USD invoice: detraction amount must be converted to PEN
    // -----------------------------------------------------------------------

    public function testInvokeConvertsDetractionAmountToPenForUsdInvoice(): void
    {
        $this->setupTaxDocument();

        // USD invoice: total 900 USD, exchange rate 3.89, detraction 12%
        // DB-stored detraction amount = 900 * 12/100 = 108 USD
        // Expected PEN mount = 108 * 3.89 = 420.12
        $voucher = new HydratedVoucher(
            saleId: 'sale-usd',
            customerDocType: '6',
            customerDocNumber: '20100000001',
            customerName: 'Test Customer SAC',
            customerAddress: $this->makeAddress(),
            customerPhone: null,
            customerEmail: null,
            companyId: '20100000000',
            companyName: 'Test Company SAC',
            companyAddress: null,
            companyPhone: null,
            branchAddress: null,
            branchPhone: null,
            voucherType: '01',
            voucherSeries: 'F001',
            voucherNumber: '1',
            voucherPaymentMethod: 1,
            voucherDate: new \DateTimeImmutable('2025-01-15'),
            voucherCurrency: 'USD',
            voucherExchangeRate: 3.89,
            voucherDiscountAmount: 0.0,
            voucherDiscountPercentage: null,
            voucherDiscountTotal: 0.0,
            voucherConsumptionSurchargeAmount: 0.0,
            voucherConsumptionSurchargePercentage: null,
            voucherTaxedAmount: 762.71,
            voucherTaxAmount: 137.29,
            voucherTaxPercentage: 18.0,
            voucherTotalTaxes: 137.29,
            voucherSaleValue: 762.71,
            voucherSaleAmount: 900.00,
            voucherOperationType: '',
            voucherDetractionCode: '022',
            voucherDetractionPercent: 12.0,
            voucherDetractionAmount: 108.0,   // USD amount stored in DB
            voucherDetractionPaymentMethod: null,
            voucherDetractionPaymentDescription: null,
            voucherDetractionValueRef: 900.0,  // USD value ref stored in DB
            voucherDetractionAccount: null,
            voucherDetractionDescription: null,
            voucherBankName: null,
            voucherBankAccount: null,
            voucherBankCci: null,
            voucherNote: null,
            voucherHash: null,
            voucherQrData: null,
            voucherStatus: null,
            voucherItems: [$this->makeItem()],
        );

        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $detraction = $invoice->getDetraccion();
        self::assertNotNull($detraction);

        // mount must be PEN: 108.00 USD * 3.89 = 420.12 PEN
        self::assertEqualsWithDelta(420.12, $detraction->getMount(), 0.01);

        // valueRef must also be PEN: 900.00 USD * 3.89 = 3501.00 PEN
        self::assertEqualsWithDelta(3501.0, $detraction->getValueRef(), 0.01);
    }

    public function testInvokeDetractionAmountUnchangedForPenInvoice(): void
    {
        $this->setupTaxDocument();

        // PEN invoice: amounts must pass through unchanged
        $voucher = $this->makeBaseVoucher(
            detractionCode: '022',
            detractionPercent: 12.0,
            detractionAmount: 108.0,
        );

        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $detraction = $invoice->getDetraccion();
        self::assertNotNull($detraction);
        self::assertEqualsWithDelta(108.0, $detraction->getMount(), 0.01);
    }

    public function testInvokeSetsVoucherNoteAsObservacion(): void
    {
        $this->setupTaxDocument();

        $voucher = new HydratedVoucher(
            saleId: 'sale-1',
            customerDocType: '6',
            customerDocNumber: '20100000001',
            customerName: 'Test Customer SAC',
            customerAddress: $this->makeAddress(),
            customerPhone: null,
            customerEmail: null,
            companyId: null,
            companyName: null,
            companyAddress: null,
            companyPhone: null,
            branchAddress: null,
            branchPhone: null,
            voucherType: '01',
            voucherSeries: 'F001',
            voucherNumber: '1',
            voucherPaymentMethod: 1,
            voucherDate: new \DateTimeImmutable('2025-01-15'),
            voucherCurrency: 'PEN',
            voucherExchangeRate: null,
            voucherDiscountAmount: 0.0,
            voucherDiscountPercentage: null,
            voucherDiscountTotal: 0.0,
            voucherConsumptionSurchargeAmount: 0.0,
            voucherConsumptionSurchargePercentage: null,
            voucherTaxedAmount: 84.75,
            voucherTaxAmount: 15.25,
            voucherTaxPercentage: 18.0,
            voucherTotalTaxes: 15.25,
            voucherSaleValue: 84.75,
            voucherSaleAmount: 100.00,
            voucherOperationType: '',
            voucherDetractionCode: null,
            voucherDetractionPercent: null,
            voucherDetractionAmount: null,
            voucherDetractionPaymentMethod: null,
            voucherDetractionPaymentDescription: null,
            voucherDetractionValueRef: null,
            voucherDetractionAccount: null,
            voucherDetractionDescription: null,
            voucherBankName: null,
            voucherBankAccount: null,
            voucherBankCci: null,
            voucherNote: 'Nota de prueba',
            voucherHash: null,
            voucherQrData: null,
            voucherStatus: null,
            voucherItems: [],
        );

        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertSame('Nota de prueba', $invoice->getObservacion());
    }

    // -----------------------------------------------------------------------
    // AC-14: InvoiceLine AllowanceCharge for discounted items
    // -----------------------------------------------------------------------

    public function testDiscountedItemHasLineAllowanceChargeWithCorrectFields(): void
    {
        $this->setupTaxDocument();

        // Item: qty=2, unitPrice=50.00, discountAmount=10.00, discountPercent=10.0
        // baseAmount = 2 * 50 = 100.00, factor = 10/100 = 0.10
        $item = new HydratedVoucherItem(
            productCode: 'PROD-001',
            productUom: 'NIU',
            quantity: 2.0,
            productName: 'Test Product',
            unitValue: 42.37,
            taxedAmount: 84.75,
            taxPercentage: 18.0,
            taxAmount: 15.25,
            taxType: '10',
            unitPrice: 50.0,
            discountAmount: 10.0,
            discountPercent: 10.0,
        );

        $voucher = $this->makeBaseVoucher(discount: 10.0, item: $item);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $details = $invoice->getDetails();
        self::assertCount(1, $details);
        $detail = $details[0];

        // setDescuento still set
        self::assertEqualsWithDelta(10.0, $detail->getDescuento(), 0.001);

        // setDescuentos (AllowanceCharge array) must be set
        $descuentos = $detail->getDescuentos();
        self::assertNotNull($descuentos);
        self::assertCount(1, $descuentos);

        $lineCharge = $descuentos[0];
        // codTipo = '02' (DISCOUNT)
        self::assertSame('02', $lineCharge->getCodTipo());
        // factor = discountPercent / 100 = 0.10
        self::assertEqualsWithDelta(0.10, $lineCharge->getFactor(), 0.0001);
        // monto = discountAmount = 10.00
        self::assertEqualsWithDelta(10.0, $lineCharge->getMonto(), 0.001);
        // montoBase = qty * unitPrice = 2 * 50 = 100.00
        self::assertEqualsWithDelta(100.0, $lineCharge->getMontoBase(), 0.001);
    }

    public function testNonDiscountedItemHasNoLineAllowanceCharge(): void
    {
        $this->setupTaxDocument();

        $item = $this->makeItem(); // discountAmount = 0.0 by default
        $voucher = $this->makeBaseVoucher(item: $item);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $details = $invoice->getDetails();
        self::assertCount(1, $details);
        $detail = $details[0];

        // No AllowanceCharge on non-discounted line
        self::assertNull($detail->getDescuentos());
        self::assertNull($detail->getDescuento());
    }

    public function testDiscountedItemWithoutPercentFallsBackToComputedFactor(): void
    {
        $this->setupTaxDocument();

        // discountPercent = null → factor computed as discountAmount / baseAmount
        // qty=2, unitPrice=50 → base=100, discount=10 → factor=0.10
        $item = new HydratedVoucherItem(
            productCode: 'PROD-001',
            productUom: 'NIU',
            quantity: 2.0,
            productName: 'Test Product',
            unitValue: 42.37,
            taxedAmount: 84.75,
            taxPercentage: 18.0,
            taxAmount: 15.25,
            taxType: '10',
            unitPrice: 50.0,
            discountAmount: 10.0,
            discountPercent: null,
        );

        $voucher = $this->makeBaseVoucher(discount: 10.0, item: $item);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        $detail = $invoice->getDetails()[0];
        $lineCharge = $detail->getDescuentos()[0];
        // factor = 10 / 100 = 0.10
        self::assertEqualsWithDelta(0.10, $lineCharge->getFactor(), 0.0001);
    }

    // -----------------------------------------------------------------------
    // AC-15: LegalMonetaryTotal AllowanceTotalAmount
    // -----------------------------------------------------------------------

    public function testMtoDescuentosSetWhenDiscountTotalIsPositive(): void
    {
        $this->setupTaxDocument();

        $voucher = $this->makeBaseVoucher(discount: 15.0);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        // mtoDescuentos maps to LegalMonetaryTotal/AllowanceTotalAmount
        self::assertEqualsWithDelta(15.0, $invoice->getMtoDescuentos(), 0.001);
    }

    public function testMtoDescuentosNotSetWhenNoDiscount(): void
    {
        $this->setupTaxDocument();

        $voucher = $this->makeBaseVoucher(discount: 0.0);
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn(
            $this->createMock(\App\Billing\Invoice\Domain\Voucher::class)
        );
        $this->voucherHydrator->method('hydrate')->willReturn($voucher);

        $invoice = $this->useCase->__invoke('td-1');

        self::assertNull($invoice->getMtoDescuentos());
    }
}
