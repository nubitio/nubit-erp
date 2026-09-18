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

        return new StockEntryProcessor(
            $container->get(InventoryService::class),
            $container->get(UnitConversionServiceInterface::class),
            $this->entityManager,
            $security,
            $container->get(StockValidationPolicyInterface::class),
            $persist,
            $container->get(ActiveReferenceGuard::class),
        );
    }
}
