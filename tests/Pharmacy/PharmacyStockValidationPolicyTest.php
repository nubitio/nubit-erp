<?php

declare(strict_types=1);

namespace App\Tests\Pharmacy;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Pharmacy\Entity\PharmacyBatch;
use App\Pharmacy\Entity\PharmacyProduct;
use App\Pharmacy\Enum\ExpiryControl;
use App\Pharmacy\Policy\PharmacyStockValidationPolicy;
use App\Pharmacy\Repository\PharmacyBatchRepository;
use App\Pharmacy\Repository\PharmacyProductRepository;
use App\Pharmacy\Service\PharmacyCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class PharmacyStockValidationPolicyTest extends TestCase
{
    public function testAHardBlockFromTheInnerPolicyIsNeverSoftened(): void
    {
        $result = $this->policy(
            ExpiryControl::Block,
            batchesExpired: true,
            inner: StockValidationResult::blocked('sin stock'),
        )->validateSale($this->product(1), 'w1', '1', '10');

        self::assertFalse($result->allowed);
        self::assertSame('sin stock', $result->reason);
    }

    public function testProductWithoutAPharmacyProfilePassesThrough(): void
    {
        $result = $this->policy(null, batchesExpired: true)->validateSale($this->product(1), 'w1', '1', '10');

        self::assertTrue($result->allowed);
        self::assertNull($result->warning);
    }

    public function testBlockControlRejectsWhenEveryBatchIsExpired(): void
    {
        $result = $this->policy(ExpiryControl::Block, batchesExpired: true)->validateSale(
            $this->product(1),
            'w1',
            '1',
            '10',
        );

        self::assertFalse($result->allowed);
        self::assertStringContainsString('vencid', (string) $result->reason);
    }

    public function testBlockControlAllowsWhenAUsableBatchExists(): void
    {
        $result = $this->policy(ExpiryControl::Block, batchesExpired: false)->validateSale(
            $this->product(1),
            'w1',
            '1',
            '10',
        );

        self::assertTrue($result->allowed);
    }

    public function testWarnControlWarnsWhenEveryBatchIsExpired(): void
    {
        $result = $this->policy(ExpiryControl::Warn, batchesExpired: true)->validateSale(
            $this->product(1),
            'w1',
            '1',
            '10',
        );

        self::assertTrue($result->allowed);
        self::assertNotNull($result->warning);
    }

    public function testBlockControlPassesThroughWhenNoBatchesAreRecorded(): void
    {
        $result = $this->policy(ExpiryControl::Block, batchesExpired: true, noBatches: true)->validateSale(
            $this->product(1),
            'w1',
            '1',
            '10',
        );

        self::assertTrue($result->allowed);
    }

    private function policy(
        ?ExpiryControl $control,
        bool $batchesExpired,
        bool $noBatches = false,
        ?StockValidationResult $inner = null,
    ): PharmacyStockValidationPolicy {
        $innerPolicy = $this->createMock(StockValidationPolicyInterface::class);
        $innerPolicy->method('validateSale')->willReturn($inner ?? StockValidationResult::allowed());

        $productRepo = $this->createMock(PharmacyProductRepository::class);
        $productRepo
            ->method('findForProduct')
            ->willReturn($control === null ? null : new PharmacyProduct(1)->setExpiryControl($control));

        $expiry = $batchesExpired ? '2000-01-01' : '2999-01-01';
        $batches = $noBatches ? [] : [new PharmacyBatch(1, 'L1', new DateTimeImmutable($expiry))];
        $batchRepo = $this->createMock(PharmacyBatchRepository::class);
        $batchRepo->method('fefoForProduct')->willReturn($batches);

        return new PharmacyStockValidationPolicy($innerPolicy, new PharmacyCatalogService($productRepo, $batchRepo));
    }

    private function product(int $id): Product
    {
        $product = new Product();
        $reflection = new \ReflectionProperty(Product::class, 'id');
        $reflection->setValue($product, (string) $id);

        return $product;
    }
}
