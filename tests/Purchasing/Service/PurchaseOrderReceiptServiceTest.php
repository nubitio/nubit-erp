<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Service;

use App\Contacts\Entity\Supplier;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\Entity\PurchaseOrderItem;
use App\Purchasing\Service\PurchaseOrderReceiptService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderReceiptServiceTest extends TestCase
{
    private function makeOrder(string ...$orderedQtys): PurchaseOrder
    {
        $order = new PurchaseOrder()
            ->setSupplier(new Supplier())
            ->setStatus(PurchaseOrder::STATUS_SENT);
        foreach ($orderedQtys as $qty) {
            $order->addItem(
                new PurchaseOrderItem()
                    ->setOrderedQty($qty)
                    ->setNameSnapshot('Item'),
            );
        }

        return $order;
    }

    private function makePurchaseItem(PurchaseOrderItem $orderItem, string $qty): PurchaseItem
    {
        return new PurchaseItem()
            ->setPurchaseOrderItem($orderItem)
            ->setQty($qty);
    }

    public function testReceiptLineIdMustBelongToTheSelectedOrder(): void
    {
        $order = $this->makeOrder('2.000');
        $line = $order->getItems()->first();
        self::assertInstanceOf(PurchaseOrderItem::class, $line);
        new \ReflectionProperty(PurchaseOrderItem::class, 'id')->setValue($line, 42);
        $item = (new PurchaseItem())->setQty('1.000');
        $item->setPurchaseOrderItemId(42);
        $purchase = (new Purchase())->setPurchaseOrder($order)->addItem($item);
        $service = new PurchaseOrderReceiptService($this->createStub(EntityManagerInterface::class));

        $service->resolveReceiptLines($purchase);
        self::assertSame($line, $item->getPurchaseOrderItem());

        $item->setPurchaseOrderItemId(43);
        $this->expectException(\Nubit\Platform\Exception\ValidationException::class);
        $service->resolveReceiptLines($purchase);
    }

    public function testApplyReceiptOnlyUpdatesLinkedLines(): void
    {
        $order = $this->makeOrder('10.000', '5.000', '2.000');
        [$lineA, $lineB, $lineC] = iterator_to_array($order->getItems());

        $purchase = new Purchase()->setPurchaseOrder($order);
        $purchase->addItem($this->makePurchaseItem($lineA, '4.000'));
        $purchase->addItem($this->makePurchaseItem($lineB, '5.000'));
        // lineC intentionally has no matching PurchaseItem in this delivery.

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        new PurchaseOrderReceiptService($entityManager)->applyReceipt($purchase);

        self::assertSame('4.000', $lineA->getReceivedQty());
        self::assertSame('5.000', $lineB->getReceivedQty());
        self::assertSame('0.000', $lineC->getReceivedQty());
        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->getStatus());
    }

    public function testApplyReceiptSkipsItemsWithoutPurchaseOrderLink(): void
    {
        $order = $this->makeOrder('10.000');
        [$line] = iterator_to_array($order->getItems());

        $purchase = new Purchase()->setPurchaseOrder($order);
        $purchase->addItem(new PurchaseItem()->setQty('3.000')); // no purchaseOrderItem link — extra item

        $entityManager = $this->createMock(EntityManagerInterface::class);

        new PurchaseOrderReceiptService($entityManager)->applyReceipt($purchase);

        self::assertSame('0.000', $line->getReceivedQty());
        self::assertSame(PurchaseOrder::STATUS_SENT, $order->getStatus());
    }

    public function testStatusBecomesReceivedWhenAllLinesFullyReceived(): void
    {
        $order = $this->makeOrder('10.000', '5.000');
        [$lineA, $lineB] = iterator_to_array($order->getItems());

        $purchase = new Purchase()->setPurchaseOrder($order);
        $purchase->addItem($this->makePurchaseItem($lineA, '10.000'));
        $purchase->addItem($this->makePurchaseItem($lineB, '5.000'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new PurchaseOrderReceiptService($entityManager)->applyReceipt($purchase);

        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $order->getStatus());
    }

    public function testTwoDeliveriesThenFullRevertRoundTrip(): void
    {
        $order = $this->makeOrder('10.000');
        [$line] = iterator_to_array($order->getItems());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new PurchaseOrderReceiptService($entityManager);

        $firstDelivery = new Purchase()->setPurchaseOrder($order);
        $firstDelivery->addItem($this->makePurchaseItem($line, '6.000'));
        $service->applyReceipt($firstDelivery);
        self::assertSame('6.000', $line->getReceivedQty());
        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->getStatus());

        $secondDelivery = new Purchase()->setPurchaseOrder($order);
        $secondDelivery->addItem($this->makePurchaseItem($line, '4.000'));
        $service->applyReceipt($secondDelivery);
        self::assertSame('10.000', $line->getReceivedQty());
        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $order->getStatus());

        $service->revertReceipt($secondDelivery);
        self::assertSame('6.000', $line->getReceivedQty());
        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->getStatus());

        $service->revertReceipt($firstDelivery);
        self::assertSame('0.000', $line->getReceivedQty());
        self::assertSame(PurchaseOrder::STATUS_SENT, $order->getStatus());
    }

    public function testRevertReceiptNeverGoesNegative(): void
    {
        $order = $this->makeOrder('10.000');
        [$line] = iterator_to_array($order->getItems());
        $line->setReceivedQty('2.000');

        $purchase = new Purchase()->setPurchaseOrder($order);
        $purchase->addItem($this->makePurchaseItem($line, '5.000')); // more than what was received

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new PurchaseOrderReceiptService($entityManager)->revertReceipt($purchase);

        self::assertSame('0.000', $line->getReceivedQty());
    }

    public function testDoesNothingWhenPurchaseHasNoLinkedPurchaseOrder(): void
    {
        $purchase = new Purchase();
        $purchase->addItem(new PurchaseItem()->setQty('5.000'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        // Should not throw despite no PurchaseOrder — items are simply skipped.
        new PurchaseOrderReceiptService($entityManager)->applyReceipt($purchase);

        self::assertNull($purchase->getPurchaseOrder());
    }
}
