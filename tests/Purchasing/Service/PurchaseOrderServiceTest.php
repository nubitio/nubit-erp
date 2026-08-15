<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Service;

use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\Entity\PurchaseOrderItem;
use App\Purchasing\Service\PurchaseOrderService;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderServiceTest extends TestCase
{
    public function testRecomputeTotalsSumsItemAmounts(): void
    {
        $order = new PurchaseOrder()->setSupplier(new \App\Contacts\Entity\Supplier());
        $order->addItem(
            new PurchaseOrderItem()
                ->setGrossAmount('100.00')
                ->setTaxAmount('18.00'),
        );
        $order->addItem(
            new PurchaseOrderItem()
                ->setGrossAmount('50.00')
                ->setTaxAmount('0.00'),
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new PurchaseOrderService($entityManager)->recomputeTotals($order);

        self::assertSame('150.00', $order->getSubtotal());
        self::assertSame('18.00', $order->getTaxTotal());
        self::assertSame('168.00', $order->getGrandTotal());
    }

    public function testTransitionToAllowsDraftToSent(): void
    {
        $order = new PurchaseOrder()->setSupplier(new \App\Contacts\Entity\Supplier());
        self::assertSame(PurchaseOrder::STATUS_DRAFT, $order->getStatus());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new PurchaseOrderService($entityManager)->transitionTo($order, PurchaseOrder::STATUS_SENT);

        self::assertSame(PurchaseOrder::STATUS_SENT, $order->getStatus());
    }

    public function testTransitionToAllowsCancelFromDraft(): void
    {
        $order = new PurchaseOrder()->setSupplier(new \App\Contacts\Entity\Supplier());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new PurchaseOrderService($entityManager)->transitionTo($order, PurchaseOrder::STATUS_CANCELLED);

        self::assertSame(PurchaseOrder::STATUS_CANCELLED, $order->getStatus());
    }

    public function testTransitionToRejectsManualTransitionToPartiallyReceived(): void
    {
        $order = new PurchaseOrder()
            ->setSupplier(new \App\Contacts\Entity\Supplier())
            ->setStatus(PurchaseOrder::STATUS_SENT);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(ValidationException::class);
        new PurchaseOrderService($entityManager)->transitionTo($order, PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
    }

    public function testTransitionToRejectsManualTransitionToReceived(): void
    {
        $order = new PurchaseOrder()
            ->setSupplier(new \App\Contacts\Entity\Supplier())
            ->setStatus(PurchaseOrder::STATUS_SENT);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(ValidationException::class);
        new PurchaseOrderService($entityManager)->transitionTo($order, PurchaseOrder::STATUS_RECEIVED);
    }

    public function testTransitionToRejectsFromTerminalState(): void
    {
        $order = new PurchaseOrder()
            ->setSupplier(new \App\Contacts\Entity\Supplier())
            ->setStatus(PurchaseOrder::STATUS_RECEIVED);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(ValidationException::class);
        new PurchaseOrderService($entityManager)->transitionTo($order, PurchaseOrder::STATUS_CANCELLED);
    }

    public function testBuildItemsFromPayloadComputesGrossAmountWithoutProduct(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $items = new PurchaseOrderService($entityManager)->buildItemsFromPayload([
            ['orderedQty' => 3, 'expectedUnitCost' => '10.00', 'nameSnapshot' => 'Insumo manual'],
        ]);

        self::assertCount(1, $items);
        self::assertSame('3.000', $items[0]->getOrderedQty());
        self::assertSame('30.00', $items[0]->getGrossAmount());
        self::assertSame('30.00', $items[0]->getTotalAmount());
        self::assertSame('Insumo manual', $items[0]->getNameSnapshot());
        self::assertSame('0.000', $items[0]->getReceivedQty());
    }
}
