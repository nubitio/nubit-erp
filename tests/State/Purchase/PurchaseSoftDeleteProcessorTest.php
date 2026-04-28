<?php

declare(strict_types=1);

namespace App\Tests\State\Purchase;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\State\ProcessorInterface;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Purchasing\State\PurchaseSoftDeleteProcessor;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PurchaseSoftDeleteProcessorTest extends TestCase
{
    /** @var ProcessorInterface<Purchase, null>&MockObject */
    private ProcessorInterface&MockObject $persistProcessor;

    public function testSoftDeleteSetsDeletedAtAndPersistsPurchase(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $purchase = new Purchase();

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with(
                self::callback(static function (Purchase $processedPurchase): bool {
                    return $processedPurchase->getDeletedAt() instanceof \DateTimeInterface;
                }),
                self::isInstanceOf(Delete::class),
                ['id' => 'purchase-1'],
                ['request_uri' => '/api/purchases/purchase-1'],
            );

        $processor = new PurchaseSoftDeleteProcessor($this->persistProcessor);

        $processor->process(
            $purchase,
            new Delete(),
            ['id' => 'purchase-1'],
            ['request_uri' => '/api/purchases/purchase-1'],
        );
        self::assertInstanceOf(DateTime::class, $purchase->getDeletedAt());
    }

    public function testSoftDeleteKeepsExistingDeletedAtTimestamp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $deletedAt = new DateTime('-1 day');
        $purchase = (new Purchase())->setDeletedAt($deletedAt);

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with($purchase, self::isInstanceOf(Delete::class), [], []);

        $processor = new PurchaseSoftDeleteProcessor($this->persistProcessor);

        $processor->process($purchase, new Delete(), [], []);

        self::assertSame($deletedAt, $purchase->getDeletedAt());
    }

    public function testSoftDeleteRetainsLinkedItemsAndOwningSideAssociation(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $purchase = new Purchase();
        $item = (new PurchaseItem())
            ->setLineNo(1)
            ->setNameSnapshot('Item kept after soft delete')
            ->setQty('1.000')
            ->setUnitCost('10.0000')
            ->setGrossAmount('10.00')
            ->setTaxAmount('0.00')
            ->setTotalAmount('10.00');
        $purchase->addItem($item);

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with(
                self::callback(static function (Purchase $processedPurchase) use ($item): bool {
                    return $processedPurchase->getDeletedAt() instanceof \DateTimeInterface
                        && $processedPurchase->getItems()->count() === 1
                        && $processedPurchase->getItems()->first() === $item
                        && $item->getPurchase() === $processedPurchase;
                }),
                self::isInstanceOf(Delete::class),
                ['id' => 'purchase-with-item'],
                [],
            );

        $processor = new PurchaseSoftDeleteProcessor($this->persistProcessor);

        $processor->process($purchase, new Delete(), ['id' => 'purchase-with-item'], []);

        self::assertSame($purchase, $item->getPurchase());
        self::assertCount(1, $purchase->getItems());
    }

    public function testPurchaseDeleteOperationUsesSoftDeleteProcessorAndNoOutput(): void
    {
        $resource = (new ReflectionClass(Purchase::class))->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if (!$operation instanceof Delete) {
                continue;
            }

            self::assertSame(PurchaseSoftDeleteProcessor::class, $operation->getProcessor());
            self::assertFalse($operation->getOutput());

            return;
        }

        self::fail('Purchase ApiResource is missing its DELETE operation.');
    }
}
