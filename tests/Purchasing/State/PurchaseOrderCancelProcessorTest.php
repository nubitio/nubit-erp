<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\State\PurchaseOrderCancelProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AllowMockObjectsWithoutExpectations]
final class PurchaseOrderCancelProcessorTest extends TestCase
{
    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private MockObject $persist;

    protected function setUp(): void
    {
        $this->persist = $this->createMock(ProcessorInterface::class);
    }

    public function testCancelsDraftOrder(): void
    {
        $order = new PurchaseOrder()->setSupplier(new \App\Contacts\Entity\Supplier());

        $this->persist->expects(self::once())->method('process');

        $result = $this->processor()->process($order, new Delete(), [], []);

        self::assertSame(PurchaseOrder::STATUS_CANCELLED, $order->getStatus());
        self::assertNotNull($order->getCancelledAt());
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertNull($result);
    }

    public function testCancelsSentOrder(): void
    {
        $order = new PurchaseOrder()
            ->setSupplier(new \App\Contacts\Entity\Supplier())
            ->setStatus(PurchaseOrder::STATUS_SENT);

        $this->persist->expects(self::once())->method('process');

        $this->processor()->process($order, new Delete(), [], []);

        self::assertSame(PurchaseOrder::STATUS_CANCELLED, $order->getStatus());
    }

    public function testRejectsCancellingPartiallyReceivedOrder(): void
    {
        $order = new PurchaseOrder()
            ->setSupplier(new \App\Contacts\Entity\Supplier())
            ->setStatus(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

        $this->persist->expects(self::never())->method('process');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->processor()->process($order, new Delete(), [], []);
    }

    public function testRejectsCancellingReceivedOrder(): void
    {
        $order = new PurchaseOrder()
            ->setSupplier(new \App\Contacts\Entity\Supplier())
            ->setStatus(PurchaseOrder::STATUS_RECEIVED);

        $this->persist->expects(self::never())->method('process');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->processor()->process($order, new Delete(), [], []);
    }

    private function processor(): PurchaseOrderCancelProcessor
    {
        return new PurchaseOrderCancelProcessor($this->persist);
    }
}
