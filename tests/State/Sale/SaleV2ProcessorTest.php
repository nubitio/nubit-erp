<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use App\Entity\Product;
use App\Entity\SaleRecord;
use App\Repository\ProductRepository;
use App\Repository\SaleRecordRepository;
use App\State\Sale\SaleProcessor;
use App\State\Sale\SaleV2Processor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SaleV2Processor::class)]
class SaleV2ProcessorTest extends TestCase
{
    private SaleProcessor&MockObject $saleProcessor;
    private SaleRecordRepository&MockObject $saleRecordRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ProductRepository&MockObject $productRepository;
    private LoggerInterface&MockObject $logger;
    private SaleV2Processor $processor;

    protected function setUp(): void
    {
        $this->saleProcessor = $this->createMock(SaleProcessor::class);
        $this->saleRecordRepository = $this->createMock(SaleRecordRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new SaleV2Processor(
            $this->saleProcessor,
            $this->saleRecordRepository,
            $this->entityManager,
            $this->productRepository,
            $this->logger,
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildMinimalRecord(): SaleRecord
    {
        $record = new SaleRecord();
        $record->setGrandTotal('100.00');
        // sellerUserId triggers setCreatedBy — leave null for minimal
        return $record;
    }

    /**
     * Make saleProcessor->process() assign a given ID to the Sale argument.
     */
    private function stubSaleProcessorAssignsId(string $id): void
    {
        $this->saleProcessor->method('process')
            ->willReturnCallback(function (mixed $sale, Operation $op) use ($id): void {
                /** @var \App\Entity\Sale $sale */
                $sale->setId($id);
            });
    }

    // ── process() guard: wrong input type ─────────────────────────────────────

    public function testProcessThrowsWhenDataIsNotSaleRecord(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SaleV2Processor expects a SaleRecord instance.');

        $this->processor->process(new \stdClass(), new Post());
    }

    // ── process() guard: SaleProcessor does not assign ID ────────────────────

    public function testProcessThrowsWhenSaleProcessorDoesNotAssignId(): void
    {
        $record = $this->buildMinimalRecord();

        // saleProcessor.process() does nothing → sale.id stays null
        $this->saleProcessor->method('process')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SaleProcessor did not assign an ID to the sale.');

        $this->processor->process($record, new Post());
    }

    // ── process() — findByAnyId returns null (race condition) ─────────────────

    public function testProcessReturnsSyntheticRecordWhenRepositoryReturnsNull(): void
    {
        $record = $this->buildMinimalRecord();

        $this->stubSaleProcessorAssignsId('sale-uuid-synthetic');
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');

        $result = $this->processor->process($record, new Post());

        $this->assertSame($record, $result);
        $this->assertSame('sale-uuid-synthetic', $result->getId());
    }

    // ── process() — happy path ────────────────────────────────────────────────

    public function testProcessHappyPathReturnsEnrichedSaleRecord(): void
    {
        $record = $this->buildMinimalRecord();
        $enriched = new SaleRecord();
        $enriched->setId('sale-uuid-enriched');

        $this->stubSaleProcessorAssignsId('sale-uuid-enriched');
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn($enriched);
        $this->saleRecordRepository->expects($this->once())
            ->method('enrichWithTaxDocuments')
            ->with([$enriched]);

        $result = $this->processor->process($record, new Post());

        $this->assertSame($enriched, $result);
    }

    // ── extractProductId() via buildSaleItems via process() ──────────────────

    public function testExtractProductIdFromIriString(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-uuid-from-iri');

        $this->productRepository->method('find')
            ->with('product-uuid-from-iri')
            ->willReturn($product);

        $record = $this->buildMinimalRecord();
        $record->setWriteItems([[
            'product' => '/api/products/product-uuid-from-iri',
            'qty' => 1,
            'unitPrice' => '10.00',
            'netAmount' => '10.00',
            'discountAmount' => '0',
        ]]);

        $enriched = new SaleRecord();
        $this->stubSaleProcessorAssignsId('sale-123');
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn($enriched);
        $this->saleRecordRepository->method('enrichWithTaxDocuments');

        // Verify SaleProcessor receives a Sale with 1 item
        $capturedSale = null;
        $this->saleProcessor->method('process')
            ->willReturnCallback(function (mixed $sale) use (&$capturedSale): void {
                /** @var \App\Entity\Sale $sale */
                $capturedSale = $sale;
                $sale->setId('sale-123');
            });

        $this->processor->process($record, new Post());

        $this->assertNotNull($capturedSale);
        $this->assertCount(1, $capturedSale->getItems());
    }

    public function testExtractProductIdFromObjectWithAtId(): void
    {
        $product = $this->createMock(Product::class);

        $this->productRepository->method('find')
            ->with('product-uuid-at-id')
            ->willReturn($product);

        $record = $this->buildMinimalRecord();
        $record->setWriteItems([[
            'product' => ['@id' => '/api/products/product-uuid-at-id'],
            'qty' => 1,
            'unitPrice' => '10.00',
            'netAmount' => '10.00',
            'discountAmount' => '0',
        ]]);

        $capturedSale = null;
        $this->saleProcessor->method('process')
            ->willReturnCallback(function (mixed $sale) use (&$capturedSale): void {
                /** @var \App\Entity\Sale $sale */
                $capturedSale = $sale;
                $sale->setId('sale-456');
            });

        $enriched = new SaleRecord();
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn($enriched);
        $this->saleRecordRepository->method('enrichWithTaxDocuments');

        $this->processor->process($record, new Post());

        $this->assertNotNull($capturedSale);
        $this->assertCount(1, $capturedSale->getItems());
    }

    public function testExtractProductIdFromObjectWithId(): void
    {
        $product = $this->createMock(Product::class);

        $this->productRepository->method('find')
            ->with('product-uuid-id-key')
            ->willReturn($product);

        $record = $this->buildMinimalRecord();
        $record->setWriteItems([[
            'product' => ['id' => 'product-uuid-id-key'],
            'qty' => 1,
            'unitPrice' => '10.00',
            'netAmount' => '10.00',
            'discountAmount' => '0',
        ]]);

        $capturedSale = null;
        $this->saleProcessor->method('process')
            ->willReturnCallback(function (mixed $sale) use (&$capturedSale): void {
                /** @var \App\Entity\Sale $sale */
                $capturedSale = $sale;
                $sale->setId('sale-789');
            });

        $enriched = new SaleRecord();
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn($enriched);
        $this->saleRecordRepository->method('enrichWithTaxDocuments');

        $this->processor->process($record, new Post());

        $this->assertNotNull($capturedSale);
        $this->assertCount(1, $capturedSale->getItems());
    }

    public function testExtractProductIdReturnsNullWhenProductRefIsNull(): void
    {
        // Item with no product key → extractProductId returns null → item skipped
        $record = $this->buildMinimalRecord();
        $record->setWriteItems([[
            // no 'product' or 'producto' key
            'qty' => 1,
            'unitPrice' => '10.00',
            'netAmount' => '10.00',
        ]]);

        $capturedSale = null;
        $this->saleProcessor->method('process')
            ->willReturnCallback(function (mixed $sale) use (&$capturedSale): void {
                /** @var \App\Entity\Sale $sale */
                $capturedSale = $sale;
                $sale->setId('sale-no-product');
            });

        $enriched = new SaleRecord();
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn($enriched);
        $this->saleRecordRepository->method('enrichWithTaxDocuments');

        $this->processor->process($record, new Post());

        $this->assertNotNull($capturedSale);
        $this->assertCount(0, $capturedSale->getItems());
    }

    // ── buildSaleItems() — product not found ─────────────────────────────────

    public function testBuildSaleItemsSkipsAndLogsWhenProductNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);

        $record = $this->buildMinimalRecord();
        $record->setWriteItems([[
            'product' => '/api/products/missing-uuid',
            'qty' => 1,
            'unitPrice' => '10.00',
            'netAmount' => '10.00',
        ]]);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Product not found'));

        $capturedSale = null;
        $this->saleProcessor->method('process')
            ->willReturnCallback(function (mixed $sale) use (&$capturedSale): void {
                /** @var \App\Entity\Sale $sale */
                $capturedSale = $sale;
                $sale->setId('sale-missing-product');
            });

        $enriched = new SaleRecord();
        $this->entityManager->method('clear');
        $this->saleRecordRepository->method('findByAnyId')->willReturn($enriched);
        $this->saleRecordRepository->method('enrichWithTaxDocuments');

        $this->processor->process($record, new Post());

        $this->assertNotNull($capturedSale);
        $this->assertCount(0, $capturedSale->getItems());
    }
}
