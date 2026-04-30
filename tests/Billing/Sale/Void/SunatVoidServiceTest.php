<?php

declare(strict_types=1);

namespace App\Tests\Billing\Sale\Void;

use App\Billing\Sale\Void\SunatVoidResponse;
use App\Billing\Sale\Void\SunatVoidService;
use App\Billing\Sale\Void\VoidStrategyInterface;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use Nubit\Platform\Exception\ServiceException;
use PHPUnit\Framework\TestCase;

final class SunatVoidServiceTest extends TestCase
{
    private function makeResponse(string $name = 'RA-20241201-00001'): SunatVoidResponse
    {
        return new SunatVoidResponse(new SummaryResponse(), $name, 'TKT-1', true, null);
    }

    public function testDispatchesToInvoiceStrategyWhenSupported(): void
    {
        $invoiceStrategy = $this->createMock(VoidStrategyInterface::class);
        $invoiceStrategy->method('supports')->with('01')->willReturn(true);
        $invoiceStrategy->expects(self::once())->method('void')
            ->with('sale-1', 'Motivo')
            ->willReturn($this->makeResponse());

        $receiptStrategy = $this->createStub(VoidStrategyInterface::class);
        $receiptStrategy->method('supports')->willReturn(false);

        $service = new SunatVoidService($invoiceStrategy, $receiptStrategy);
        $result = $service->void('01', 'sale-1', 'Motivo');

        self::assertTrue($result->success);
    }

    public function testDispatchesToReceiptStrategyWhenInvoiceStrategyDeclines(): void
    {
        $invoiceStrategy = $this->createStub(VoidStrategyInterface::class);
        $invoiceStrategy->method('supports')->willReturn(false);

        $receiptStrategy = $this->createMock(VoidStrategyInterface::class);
        $receiptStrategy->method('supports')->with('03')->willReturn(true);
        $receiptStrategy->expects(self::once())->method('void')
            ->with('sale-2', 'Otro motivo')
            ->willReturn($this->makeResponse());

        $service = new SunatVoidService($invoiceStrategy, $receiptStrategy);
        $result = $service->void('03', 'sale-2', 'Otro motivo');

        self::assertTrue($result->success);
    }

    public function testThrowsWhenNoStrategySupports(): void
    {
        $invoiceStrategy = $this->createStub(VoidStrategyInterface::class);
        $invoiceStrategy->method('supports')->willReturn(false);

        $receiptStrategy = $this->createStub(VoidStrategyInterface::class);
        $receiptStrategy->method('supports')->willReturn(false);

        $service = new SunatVoidService($invoiceStrategy, $receiptStrategy);

        $this->expectException(ServiceException::class);
        $service->void('99', 'sale-3', 'Motivo');
    }
}
