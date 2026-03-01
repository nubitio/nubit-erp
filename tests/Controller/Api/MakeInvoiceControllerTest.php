<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\MakeInvoice;
use App\EInvoicing\Service\InvoiceCreationService;
use App\EInvoicing\Service\SaleV2Loader;
use App\Entity\Sale;
use App\Repository\SaleRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MakeInvoiceControllerTest extends TestCase
{
    public function testReturnsErrorWhenSaleDoesNotExist(): void
    {
        $saleRepository = $this->createMock(SaleRepository::class);
        $saleRepository->method('find')->with('sale-404')->willReturn(null);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->with('sale-404')->willReturn(null);

        $invoiceCreationService = (new \ReflectionClass(InvoiceCreationService::class))->newInstanceWithoutConstructor();

        $controller = new MakeInvoice();
        $request = Request::create('/api/sales/sale-404/invoice', 'POST');
        $request->attributes->set('id', 'sale-404');
        $response = $controller(
            $request,
            $saleRepository,
            $invoiceCreationService,
            $saleV2Loader,
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

        $saleRepository = $this->createMock(SaleRepository::class);
        $saleRepository->method('find')->with('sale-1')->willReturn($sale);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->expects(self::never())->method('loadAsSale');

        $invoiceCreationService = (new \ReflectionClass(InvoiceCreationService::class))->newInstanceWithoutConstructor();

        $controller = new MakeInvoice();
        $request = Request::create('/api/sales/sale-1/invoice', 'POST');
        $request->attributes->set('id', 'sale-1');
        $response = $controller(
            $request,
            $saleRepository,
            $invoiceCreationService,
            $saleV2Loader,
        );

        $payload = json_decode((string) $response->getContent(), true);
        self::assertFalse($payload['success']);
        self::assertSame('La venta ya tiene un comprobante emitido.', $payload['message']);
    }

    public function testFallsBackToSaleV2LoaderWhenLegacySaleNotFound(): void
    {
        $v2Sale = (new Sale())
            ->setId('v2-uuid-sale')
            ->setHasInvoice(true)
            ->setInvoice('B001-42');

        $saleRepository = $this->createMock(SaleRepository::class);
        $saleRepository->method('find')->with('v2-uuid-sale')->willReturn(null);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->with('v2-uuid-sale')->willReturn($v2Sale);

        $invoiceCreationService = (new \ReflectionClass(InvoiceCreationService::class))->newInstanceWithoutConstructor();

        $controller = new MakeInvoice();
        $request = Request::create('/api/sales/v2-uuid-sale/invoice', 'POST');
        $request->attributes->set('id', 'v2-uuid-sale');
        $response = $controller(
            $request,
            $saleRepository,
            $invoiceCreationService,
            $saleV2Loader,
        );

        // The V2 sale already has an invoice, so it should return the "already invoiced" error.
        $payload = json_decode((string) $response->getContent(), true);
        self::assertFalse($payload['success']);
        self::assertSame('La venta ya tiene un comprobante emitido.', $payload['message']);
    }
}
