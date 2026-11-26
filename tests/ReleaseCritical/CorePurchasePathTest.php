<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Auth\OperationalBranchGuard;
use App\Organization\Repository\BranchRepository;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Purchasing\Service\PurchaseOrderReceiptService;
use App\Purchasing\Service\PurchaseReceiptSequenceService;
use App\Purchasing\Service\PurchaseSupplierDocumentValidator;
use App\Purchasing\State\PurchaseInventoryService;
use App\Purchasing\State\PurchaseProcessor;
use App\Purchasing\State\PurchaseVoidProcessor;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use App\Tests\ReleaseCritical\Support\CoreSaleWorld;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Kernel characterization: goods receipt → stock ledger → void reversal.
 */
final class CorePurchasePathTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CoreSaleFixture $fixture;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->fixture = new CoreSaleFixture(
            $this->entityManager,
            static::getContainer()->get(TenantSettingsService::class),
        );
    }

    public function testGoodsReceiptIncreasesStockAndKeysMovementByPurchaseId(): void
    {
        $world = $this->fixture->seed('2.000000');
        $purchase = $this->newReceipt($world, '5');

        $result = $this->purchaseProcessor($world)->process($purchase, new Post());
        self::assertInstanceOf(Purchase::class, $result);

        self::assertEqualsWithDelta(7.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);

        $movement = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT source_id, source_type, movement_type, qty_delta_base FROM stock_movements WHERE product_id = :p ORDER BY created_at DESC LIMIT 1',
            ['p' => (string) $world->product->getId()],
        );
        self::assertIsArray($movement);
        self::assertSame('PURCHASE_IN', $movement['movement_type']);
        self::assertSame('PURCHASE_ITEM', $movement['source_type']);
        $purchaseId = (string) $result->getId();
        if ($purchaseId === '') {
            self::fail('The persisted purchase has no ID.');
        }
        self::assertStringStartsWith($purchaseId, (string) $movement['source_id']);
        self::assertEqualsWithDelta(5.0, (float) $movement['qty_delta_base'], 0.001);
    }

    public function testVoidingPurchaseRestoresStock(): void
    {
        $world = $this->fixture->seed('2.000000');
        $purchase = $this->purchaseProcessor($world)->process($this->newReceipt($world, '5'), new Post());
        self::assertInstanceOf(Purchase::class, $purchase);

        static::getContainer()->get(PurchaseVoidProcessor::class)->process($purchase, new Delete());
        $this->entityManager->clear();

        self::assertEqualsWithDelta(2.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);

        $voided = $this->entityManager->find(Purchase::class, $purchase->getId());
        self::assertInstanceOf(Purchase::class, $voided);
        self::assertSame('VOIDED', $voided->getStatus());
    }

    private function newReceipt(CoreSaleWorld $world, string $qty): Purchase
    {
        $item = new PurchaseItem()
            ->setProduct($world->product)
            ->setQty($qty)
            ->setUnitCost('8.0000')
            ->setGrossAmount('40.00')
            ->setTotalAmount('40.00')
            ->setNameSnapshot($world->product->getName())
            ->setLineNo(1);

        return new Purchase()
            ->setWarehouse($world->warehouse)
            ->setBranch($world->branch)
            ->setSupplier($this->fixture->extraSupplier())
            ->setBuyerUser($world->user)
            ->setSupplierDocType('01')
            ->setSupplierDocSeries('F001')
            ->setSupplierDocNumber((string) random_int(1000, 9999))
            ->setSubtotal('40.00')
            ->setGrandTotal('40.00')
            ->addItem($item);
    }

    private function purchaseProcessor(CoreSaleWorld $world): PurchaseProcessor
    {
        $container = static::getContainer();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($world->user);

        /** @var ProcessorInterface<mixed, mixed> $persist */
        $persist = $container->get('api_platform.doctrine.orm.state.persist_processor');
        $branchContext = $this->createStub(BranchContextServiceInterface::class);

        $branchContext->method('getCurrentBranchId')->willReturn($world->branch->getId());

        return new PurchaseProcessor(
            $persist,
            $security,
            new OperationalBranchGuard($branchContext, $container->get(BranchRepository::class)),
            $container->get(PurchaseInventoryService::class),
            $container->get(PurchaseReceiptSequenceService::class),
            $container->get(PurchaseSupplierDocumentValidator::class),
            $container->get(ActiveReferenceGuard::class),
            $container->get(PurchaseOrderReceiptService::class),
            $container->get(MessageBusInterface::class),
        );
    }
}
