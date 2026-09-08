<?php

declare(strict_types=1);

namespace App\Tests\Accounting\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

final class DefaultPostingServiceTest extends TestCase
{
    public function testCashSaleDebitsCajaAndCreditsSalesAndIgv(): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_SALE,
            sourceId: 'sale-1',
            kind: EconomicEvent::SALE_CASH,
            occurredOn: '2026-08-21',
            description: 'Venta B001-1',
            total: '118.00',
            tax: '18.00',
            cogs: '40.00',
        ));

        self::assertSame('101', $lines[0]['code']);
        self::assertSame('118.00', $lines[0]['debit']);
        self::assertSame('70111', $lines[1]['code']);
        self::assertSame('100.00', $lines[1]['credit']);
        self::assertSame('40111', $lines[2]['code']);
        self::assertSame('18.00', $lines[2]['credit']);
        self::assertSame('691', $lines[3]['code']);
        self::assertSame('40.00', $lines[3]['debit']);
        self::assertSame('201', $lines[4]['code']);
        self::assertSame('40.00', $lines[4]['credit']);
        $this->assertBalanced($lines);
    }

    public function testPurchaseDebitsInventoryAndIgv(): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_PURCHASE,
            sourceId: 'po-1',
            kind: EconomicEvent::PURCHASE,
            occurredOn: '2026-08-21',
            description: 'Compra 12',
            total: '236.00',
            tax: '36.00',
        ));

        self::assertSame('201', $lines[0]['code']);
        self::assertSame('200.00', $lines[0]['debit']);
        self::assertSame('421', $lines[2]['code']);
        self::assertSame('236.00', $lines[2]['credit']);
        $this->assertBalanced($lines);
    }

    public function testReceivableCollectionInCashDebitsCajaAndCreditsReceivable(): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: 'payment-9',
            kind: EconomicEvent::RECEIVABLE_COLLECT_CASH,
            occurredOn: '2026-09-08',
            description: 'Cobro B001-1',
            total: '90.00',
        ));

        self::assertSame('101', $lines[0]['code']);
        self::assertSame('90.00', $lines[0]['debit'] ?? null);
        self::assertSame('121', $lines[1]['code']);
        self::assertSame('90.00', $lines[1]['credit'] ?? null);
        self::assertCount(2, $lines);
        $this->assertBalanced($lines);
    }

    public function testReceivableCollectionByBankDebitsCuentaCorrienteNotCaja(): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: 'payment-10',
            kind: EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: '2026-09-08',
            description: 'Cobro B001-2',
            total: '150.00',
        ));

        self::assertSame('1041', $lines[0]['code']);
        self::assertSame('150.00', $lines[0]['debit'] ?? null);
        self::assertSame('121', $lines[1]['code']);
        self::assertSame('150.00', $lines[1]['credit'] ?? null);
        $this->assertBalanced($lines);
    }

    /**
     * @param list<array{code: string, debit?: string, credit?: string, memo?: string}> $lines
     */
    private function assertBalanced(array $lines): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        self::assertEqualsWithDelta($debit, $credit, 0.001);
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
