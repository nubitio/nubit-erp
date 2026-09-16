<?php

declare(strict_types=1);

namespace App\Tests\Sales\Controller\Api;

use App\Sales\Controller\Api\MakeSaleInvoice;
use App\Sales\Domain\RequestedVoucherType;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Port\Result\InvoiceCreationResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MakeSaleInvoiceControllerTest extends TestCase
{
    public function testPassesRequestedVoucherTypeFromJsonBody(): void
    {
        $captured = 'not-called';
        $invoiceCreator = $this->createMock(InvoiceCreatorInterface::class);
        $invoiceCreator
            ->expects(self::once())
            ->method('createInvoiceForSaleId')
            ->willReturnCallback(static function (string $saleId, ?RequestedVoucherType $type) use (
                &$captured,
            ): InvoiceCreationResult {
                $captured = $type;

                return InvoiceCreationResult::success('ok');
            });

        $controller = new MakeSaleInvoice($invoiceCreator);
        $request = Request::create('/api/sales/sale-1/invoice', 'POST', content: '{"requestedVoucherType":"BOLETA"}');
        $request->attributes->set('id', 'sale-1');

        $controller($request);

        static::assertSame(RequestedVoucherType::BOLETA, $captured);
    }

    public function testPassesNullWhenBodyIsEmpty(): void
    {
        $captured = 'not-called';
        $invoiceCreator = $this->createMock(InvoiceCreatorInterface::class);
        $invoiceCreator
            ->expects(self::once())
            ->method('createInvoiceForSaleId')
            ->willReturnCallback(static function (string $saleId, ?RequestedVoucherType $type) use (
                &$captured,
            ): InvoiceCreationResult {
                $captured = $type;

                return InvoiceCreationResult::success('ok');
            });

        $controller = new MakeSaleInvoice($invoiceCreator);
        $request = Request::create('/api/sales/sale-1/invoice', 'POST', content: '{}');
        $request->attributes->set('id', 'sale-1');

        $controller($request);

        static::assertNull($captured);
    }

    public function testPassesNullWhenValueIsUnrecognised(): void
    {
        $captured = 'not-called';
        $invoiceCreator = $this->createMock(InvoiceCreatorInterface::class);
        $invoiceCreator
            ->expects(self::once())
            ->method('createInvoiceForSaleId')
            ->willReturnCallback(static function (string $saleId, ?RequestedVoucherType $type) use (
                &$captured,
            ): InvoiceCreationResult {
                $captured = $type;

                return InvoiceCreationResult::success('ok');
            });

        $controller = new MakeSaleInvoice($invoiceCreator);
        $request = Request::create(
            '/api/sales/sale-1/invoice',
            'POST',
            content: '{"requestedVoucherType":"NOT_A_TYPE"}',
        );
        $request->attributes->set('id', 'sale-1');

        $controller($request);

        static::assertNull($captured);
    }
}
