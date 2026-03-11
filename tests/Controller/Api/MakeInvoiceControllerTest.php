<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\MakeInvoice;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\EInvoicing\Service\SaleV2Loader;
use App\Entity\Sale;
use App\State\Sale\DetractionCalculatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MakeInvoiceControllerTest extends TestCase
{
    public function testReturnsErrorWhenSaleDoesNotExist(): void
    {
        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->with('sale-404')->willReturn(null);

        $invoiceCreationService = $this->createMock(InvoiceCreationServiceInterface::class);

        $detractionCalculator = $this->createMock(DetractionCalculatorInterface::class);
        $detractionCalculator->expects(self::never())->method('applyFromItems');

        $controller = new MakeInvoice();
        $request = Request::create('/api/sales/sale-404/invoice', 'POST');
        $request->attributes->set('id', 'sale-404');
        $response = $controller(
            $request,
            $invoiceCreationService,
            $saleV2Loader,
            $detractionCalculator,
        );

        $payload = json_decode((string) $response->getContent(), true);
        self::assertFalse($payload['success']);
        self::assertSame('La venta no existe.', $payload['message']);
    }

    public function testReturnsErrorWhenSaleAlreadyHasInvoice(): void
    {
        $sale = (new Sale())
            ->setHasInvoice(true)
            ->setInvoice('F001-123');

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->with('sale-1')->willReturn($sale);

        $invoiceCreationService = $this->createMock(InvoiceCreationServiceInterface::class);

        $detractionCalculator = $this->createMock(DetractionCalculatorInterface::class);
        $detractionCalculator->expects(self::never())->method('applyFromItems');

        $controller = new MakeInvoice();
        $request = Request::create('/api/sales/sale-1/invoice', 'POST');
        $request->attributes->set('id', 'sale-1');
        $response = $controller(
            $request,
            $invoiceCreationService,
            $saleV2Loader,
            $detractionCalculator,
        );

        $payload = json_decode((string) $response->getContent(), true);
        self::assertFalse($payload['success']);
        self::assertSame('La venta ya tiene un comprobante emitido.', $payload['message']);
    }

    /**
     * Asserts that when a sale exists only in the V2 `sales` table (not in `movimiento`),
     * the controller resolves it successfully via SaleV2Loader — no ORM fallback needed.
     */
    public function testV2LoaderIsTriedFirstAndOrmIsNotNeeded(): void
    {
        $v2Sale = (new Sale())
            ->setId('v2-uuid-sale')
            ->setHasInvoice(false);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        // V2 loader returns the sale — ORM must never be consulted.
        $saleV2Loader->expects(self::once())
            ->method('loadAsSale')
            ->with('v2-uuid-sale')
            ->willReturn($v2Sale);

        $invoiceCreationService = $this->createMock(InvoiceCreationServiceInterface::class);
        $invoiceCreationService->method('createForSale')
            ->with($v2Sale)
            ->willReturn(['success' => true, 'message' => 'Comprobante emitido.']);

        $detractionCalculator = $this->createMock(DetractionCalculatorInterface::class);
        $detractionCalculator->expects(self::once())->method('applyFromItems')->with($v2Sale);

        $controller = new MakeInvoice();
        $request = Request::create('/api/sales/v2-uuid-sale/invoice', 'POST');
        $request->attributes->set('id', 'v2-uuid-sale');
        $response = $controller(
            $request,
            $invoiceCreationService,
            $saleV2Loader,
            $detractionCalculator,
        );

        $payload = json_decode((string) $response->getContent(), true);
        self::assertTrue($payload['success']);
        self::assertSame('Comprobante emitido.', $payload['message']);
    }
}
