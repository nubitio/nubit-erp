<?php

declare(strict_types=1);

namespace App\Tests\Accounting\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

final class SupplierBillReversalPostingTest extends TestCase
{
    public function testDirectSupplierBillReversalReopensPayableAndReversesExpense(): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_SUPPLIER_BILL_REVERSAL,
            sourceId: 'supplier-bill-1',
            kind: EconomicEvent::SUPPLIER_BILL_REVERSE,
            occurredOn: '2026-09-10',
            description: 'Reversión de factura',
            total: '118.00',
        ));

        self::assertSame('421', $lines[0]['code']);
        self::assertSame('118.00', $lines[0]['debit'] ?? null);
        self::assertSame('659', $lines[1]['code']);
        self::assertSame('118.00', $lines[1]['credit'] ?? null);
        self::assertSame(2, count($lines));
    }

    private function service(): DefaultPostingService
    {
        $reflection = new ReflectionClass(DefaultPostingService::class);
        /** @var DefaultPostingService $service */
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('logger')->setValue($service, new NullLogger());

        return $service;
    }
}
