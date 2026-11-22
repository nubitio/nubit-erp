<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use ApiPlatform\Metadata\Post;
use App\Catalog\Entity\Product;
use App\Inventory\Dto\PriceItemDto;
use App\Inventory\Dto\PricesDto;
use App\Inventory\Entity\PriceList;
use App\Inventory\Entity\PriceListItem;
use App\Inventory\Repository\PriceListItemRepository;
use App\Inventory\State\PriceListProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Nubit\AdminBundle\Audit\Entity\AuditLog;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PriceListProcessorTest extends TestCase
{
    private PriceList $list;
    private Product $pollo;
    private Product $pechuga;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var array<string, PriceListItem> */
    private array $existing = [];

    protected function setUp(): void
    {
        $this->list = new PriceList()->setName('SRA MARCIA');
        new ReflectionProperty(PriceList::class, 'id')->setValue($this->list, 3);
        $this->pollo = $this->product('pollo', 'POLLO ENTERO X KG');
        $this->pechuga = $this->product('pechuga', 'PECHUGA DE POLLO');

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('find')->willReturn($this->list);
        $products = $this->createStub(EntityRepository::class);
        $products->method('findBy')->willReturn([$this->pollo, $this->pechuga]);
        $this->em->method('getRepository')->willReturn($products);
    }

    private function product(string $id, string $name): Product
    {
        $product = new Product();
        $product->setName($name);
        new ReflectionProperty(Product::class, 'id')->setValue($product, $id);

        return $product;
    }

    private function dto(string $polloPrice = '10.4000', string $pechugaPrice = '15.2000'): PricesDto
    {
        return new PricesDto(priceListId: 3, prices: [
            new PriceItemDto('pollo', $polloPrice),
            new PriceItemDto('pechuga', $pechugaPrice),
        ]);
    }

    private function processor(?HubInterface $hub = null): PriceListProcessor
    {
        $items = $this->createStub(PriceListItemRepository::class);
        $items->method('byProduct')->willReturn($this->existing);

        return new PriceListProcessor(
            $this->em,
            $items,
            $hub ?? $this->createStub(HubInterface::class),
            new NullLogger(),
            $this->createStub(TokenStorageInterface::class),
        );
    }

    public function testUpdatesExistingPricesAndAddsNewOnesInOneTransaction(): void
    {
        $this->existing = ['pollo' => new PriceListItem($this->list, $this->pollo, '9.8000')];

        $persisted = [];
        $this->em
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->em->expects(self::once())->method('beginTransaction');
        $this->em->expects(self::once())->method('flush');
        $this->em->expects(self::once())->method('commit');
        $this->em->expects(self::never())->method('rollback');

        $response = $this->processor()->process($this->dto(), new Post());

        self::assertTrue($response->success);
        self::assertSame('10.4000', $this->existing['pollo']->getPrice());
        $added = array_values(array_filter($persisted, static fn(object $e): bool => $e instanceof PriceListItem));
        self::assertCount(1, $added);
        self::assertSame('15.2000', $added[0]->getPrice());
        self::assertSame($this->pechuga, $added[0]->getProduct());
    }

    public function testARealChangeLeavesAnAuditEntryOnThePriceListInTheSameTransaction(): void
    {
        $this->existing = ['pollo' => new PriceListItem($this->list, $this->pollo, '9.8000')];

        $audit = null;
        $this->em
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$audit): void {
                if ($entity instanceof AuditLog) {
                    $audit = $entity;
                }
            });
        $order = [];
        $this->em
            ->method('flush')
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'flush';
            });
        $this->em
            ->method('commit')
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'commit';
            });

        $this->processor()->process($this->dto(), new Post());

        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertSame('pricelist', $audit->getResource());
        self::assertSame('3', $audit->getResourceId());
        self::assertSame(['before' => '9.8000', 'after' => '10.4000'], $audit->getChanges()['POLLO ENTERO X KG']);
        self::assertSame(['before' => null, 'after' => '15.2000'], $audit->getChanges()['PECHUGA DE POLLO']);
        self::assertSame(['flush', 'commit'], $order);
    }

    public function testResavingTheSamePricesLeavesNoAuditEntry(): void
    {
        $this->existing = [
            'pollo' => new PriceListItem($this->list, $this->pollo, '10.4000'),
            'pechuga' => new PriceListItem($this->list, $this->pechuga, '15.2000'),
        ];
        $this->em->expects(self::never())->method('persist');

        $response = $this->processor()->process($this->dto(), new Post());

        self::assertTrue($response->success);
    }

    public function testASaveThatFailsIsRolledBackAndNotAnnounced(): void
    {
        $this->em->method('flush')->willThrowException(new \RuntimeException('constraint violation'));
        $this->em->expects(self::once())->method('rollback');
        $this->em->expects(self::never())->method('commit');
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $response = $this->processor($hub)->process($this->dto(), new Post());

        self::assertFalse($response->success);
        self::assertStringContainsString('Error', $response->message);
    }

    public function testAnUnknownPriceListIsRefused(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::once())->method('rollback');
        $this->em = $em;

        self::assertFalse($this->processor()->process($this->dto(), new Post())->success);
    }

    public function testAnUnknownProductIsRefused(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($this->list);
        $products = $this->createStub(EntityRepository::class);
        $products->method('findBy')->willReturn([$this->pollo]);
        $em->method('getRepository')->willReturn($products);
        $em->expects(self::once())->method('rollback');
        $this->em = $em;

        self::assertFalse($this->processor()->process($this->dto(), new Post())->success);
    }

    public function testAnEmptySaveCommitsWithoutTouchingAnything(): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');
        $this->em->expects(self::once())->method('commit');

        $response = $this->processor()->process(new PricesDto(priceListId: 3, prices: []), new Post());

        self::assertTrue($response->success);
    }

    public function testPublishesAPriceListUpdateAfterTheCommit(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getPublicUrl')->willReturn('https://tienda.example.test/.well-known/mercure');
        $hub
            ->expects(self::once())
            ->method('publish')
            ->with(self::callback(
                static fn(Update $update): bool => [
                    'https://tienda.example.test/api/price-lists/3',
                ] === $update->getTopics(),
            ));

        self::assertTrue($this->processor($hub)->process($this->dto(), new Post())->success);
    }

    public function testAHubOutageDoesNotUndoASavedPriceList(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('getPublicUrl')->willReturn('https://tienda.example.test/.well-known/mercure');
        $hub->method('publish')->willThrowException(new \RuntimeException('hub down'));
        $this->em->expects(self::never())->method('rollback');

        self::assertTrue($this->processor($hub)->process($this->dto(), new Post())->success);
    }
}
