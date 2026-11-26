<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Contract\UnitConversionServiceInterface;
use App\Inventory\Core\Enum\StockEntryType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Entity\StockEntry;
use App\Inventory\Entity\StockEntryItem;
use App\Inventory\State\StockEntryProcessor;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use App\Tests\ReleaseCritical\Support\CoreSaleWorld;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CoreInventoryPathTest extends KernelTestCase
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

    public function testReceiptIncreasesOnHand(): void
    {
        $world = $this->fixture->seed('5.000000');
        $this->processor($world)->process($this->entry($world, StockEntryType::RECEIPT, '3'), new Post());

        self::assertEqualsWithDelta(8.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);

        $container = static::getContainer();
        $moves = $container->get(\App\Inventory\Repository\StockMovementRepository::class);
        $warehouseId = (string) $world->warehouse->getId();
        $branchId = (string) $world->branch->getId();
        self::assertSame(1, $moves->countKardex(['warehouse' => $warehouseId, 'branchId' => $branchId]));
        self::assertSame(0, $moves->countKardex(['warehouse' => $warehouseId, 'branchId' => 'other']));
        self::assertSame([], $moves->findKardexForExport(['warehouse' => $warehouseId, 'branchId' => 'other']));
        $summary = $container->get(\App\Inventory\Service\KardexSummaryService::class);
        self::assertSame(
            0,
            $summary->getSummary((string) $world->product->getId(), $warehouseId, branchId: 'other')['movementCount'],
        );
    }

    public function testTransferMovesStockBetweenWarehouses(): void
    {
        $world = $this->fixture->seed('10.000000');
        $target = $this->fixture->extraWarehouse($world);
        $entry = $this->entry($world, StockEntryType::TRANSFER, '4');
        $entry->setTargetWarehouse($target);
        $this->processor($world)->process($entry, new Post());

        self::assertEqualsWithDelta(6.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
        self::assertEqualsWithDelta(4.0, (float) $this->fixture->onHand($target, $world->product), 0.001);
    }

    public function testCrossBranchTransferRequiresOperatingAccessAtDestination(): void
    {
        $source = $this->fixture->seed('10.000000');
        $destination = $this->fixture->seed('5.000000');
        $sourceBranchId = (string) $source->branch->getId();
        $destinationBranchId = (string) $destination->branch->getId();
        $destinationWarehouseId = (string) $destination->warehouse->getId();

        $source->user->assignOperatingBranches([$sourceBranchId], $sourceBranchId);
        $source->user->assignReadOnlyBranches([$destinationBranchId]);
        $this->entityManager->detach($destination->warehouse);
        $filters = $this->entityManager->getFilters();
        $filters->enable('branch_filter')->setParameterList('branchIds', [$sourceBranchId]);

        try {
            $entry = $this->entry($source, StockEntryType::TRANSFER, '4');
            $entry->setTargetWarehouseId($destinationWarehouseId);
            try {
                $this->processor($source)->process($entry, new Post());
                self::fail('A read-only assignment must not allow writing stock to that branch.');
            } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException) {
                self::assertEqualsWithDelta(
                    10.0,
                    (float) $this->fixture->onHand($source->warehouse, $source->product),
                    0.001,
                );
            }

            $source->user->assignOperatingBranches([$sourceBranchId, $destinationBranchId], $sourceBranchId);
            $this->processor($source)->process($entry, new Post());

            self::assertEqualsWithDelta(
                6.0,
                (float) $this->fixture->onHand($source->warehouse, $source->product),
                0.001,
            );
            self::assertEqualsWithDelta(
                4.0,
                (float) $this->fixture->onHand($destination->warehouse, $source->product),
                0.001,
            );
            self::assertTrue($filters->isEnabled('branch_filter'));

            $entries = $this->entityManager->getRepository(StockEntry::class);
            $lines = $this->entityManager->getRepository(StockEntryItem::class);
            self::assertCount(1, $entries->findBy(['id' => $entry->getId()]));
            self::assertCount(1, $lines->findBy(['entry' => $entry]));

            $filters->getFilter('branch_filter')->setParameterList('branchIds', [$destinationBranchId]);
            self::assertSame([], $entries->findBy(['id' => $entry->getId()]));
            self::assertSame([], $lines->findBy(['entry' => $entry]));
        } finally {
            $filters->disable('branch_filter');
        }
    }

    public function testStockItemsAreRestrictedToTheSelectedWarehouseBranch(): void
    {
        $first = $this->fixture->seed('5.000000');
        $second = $this->fixture->seed('7.000000');
        $repository = $this->entityManager->getRepository(\App\Inventory\Entity\StockItem::class);
        $filters = $this->entityManager->getFilters();
        $filter = $filters->enable('branch_filter');

        try {
            $filter->setParameterList('branchIds', [(string) $first->branch->getId()]);
            self::assertCount(1, $repository->findBy(['warehouse' => $first->warehouse]));
            self::assertSame([], $repository->findBy(['warehouse' => $second->warehouse]));

            $filter->setParameterList('branchIds', [(string) $second->branch->getId()]);
            self::assertCount(1, $repository->findBy(['warehouse' => $second->warehouse]));
        } finally {
            $filters->disable('branch_filter');
        }
    }

    public function testIssueWithInsufficientStockDoesNotChangeOnHand(): void
    {
        $world = $this->fixture->seed('1.000000');

        try {
            $this->processor($world)->process($this->entry($world, StockEntryType::ISSUE, '5'), new Post());
            self::fail('Expected insufficient-stock rejection.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertStringContainsString('Stock insuficiente', $exception->getMessage());
        }

        self::assertEqualsWithDelta(1.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
    }

    private function entry(CoreSaleWorld $world, StockEntryType $type, string $qty): StockEntry
    {
        return new StockEntry()
            ->setType($type)
            ->setWarehouse($world->warehouse)
            ->addItem(
                new StockEntryItem()
                    ->setProduct($world->product)
                    ->setQty($qty),
            );
    }

    private function processor(CoreSaleWorld $world): StockEntryProcessor
    {
        $container = static::getContainer();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($world->user);
        $security->method('isGranted')->willReturn(true);

        /** @var ProcessorInterface<mixed, mixed> $persist */
        $persist = $container->get('api_platform.doctrine.orm.state.persist_processor');

        $context = $this->createStub(\App\Identity\Auth\BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn($world->branch->getId());

        return new StockEntryProcessor(
            $container->get(InventoryService::class),
            $container->get(UnitConversionServiceInterface::class),
            $this->entityManager,
            $security,
            $container->get(StockValidationPolicyInterface::class),
            $persist,
            $container->get(ActiveReferenceGuard::class),
            new \App\Identity\Auth\OperationalBranchGuard(
                $context,
                $container->get(\App\Organization\Repository\BranchRepository::class),
            ),
        );
    }
}
