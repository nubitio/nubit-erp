<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Context\BranchContextAdapter;
use App\AI\Context\BranchContextInterface;
use App\AI\Dto\CashRegisterStatusDto;
use App\AI\Dto\ProductStockDto;
use App\AI\Dto\ProductWarehouseStockDto;
use App\Efact\Dto\ProductSearchResultDto;
use App\Efact\Dto\RecentSaleDto;
use App\Efact\Dto\SalesSummaryDto;
use App\Identity\Auth\BranchContextServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * Pure value-object and adapter tests for the AI bounded context.
 */
final class AIDomainTest extends TestCase
{
    // ── DTOs ──────────────────────────────────────────────────────────────────

    public function testCashRegisterStatusDtoHoldsAllFields(): void
    {
        $dto = new CashRegisterStatusDto(
            cashRegisterId: 1,
            cashRegisterName: 'Caja Principal',
            status: 'open',
            shiftId: 42,
            openedAt: '2026-04-30 08:00:00',
            openedBy: 'Juan',
            closedAt: null,
            openingBalance: 200.00,
            expectedAmount: 1500.00,
            countedAmount: 1498.50,
            difference: -1.50,
        );

        self::assertSame(1, $dto->cashRegisterId);
        self::assertSame('Caja Principal', $dto->cashRegisterName);
        self::assertSame('open', $dto->status);
        self::assertSame(42, $dto->shiftId);
        self::assertSame('2026-04-30 08:00:00', $dto->openedAt);
        self::assertNull($dto->closedAt);
        self::assertEqualsWithDelta(-1.50, $dto->difference ?? 0, 0.001);
    }

    public function testCashRegisterStatusDtoNullableFields(): void
    {
        $dto = new CashRegisterStatusDto(
            cashRegisterId: 2,
            cashRegisterName: 'Caja 2',
            status: 'closed',
            shiftId: null,
            openedAt: null,
            openedBy: null,
            closedAt: null,
            openingBalance: null,
            expectedAmount: null,
            countedAmount: null,
            difference: null,
        );

        self::assertNull($dto->shiftId);
        self::assertNull($dto->openedAt);
        self::assertNull($dto->difference);
    }

    public function testProductWarehouseStockDtoHoldsFields(): void
    {
        $dto = new ProductWarehouseStockDto(
            warehouseId: 'wh-1',
            warehouseName: 'Almacén Central',
            stock: 25.5,
            minStock: 5.0,
        );

        self::assertSame('wh-1', $dto->warehouseId);
        self::assertSame('Almacén Central', $dto->warehouseName);
        self::assertEqualsWithDelta(25.5, $dto->stock, 0.001);
        self::assertEqualsWithDelta(5.0, $dto->minStock, 0.001);
    }

    public function testProductStockDtoHoldsFields(): void
    {
        $warehouse = new ProductWarehouseStockDto('wh-1', 'Central', 10.0, 2.0);
        $dto = new ProductStockDto(
            productId: 'prod-1',
            code: 'SKU001',
            name: 'Laptop 15"',
            totalStock: 10.0,
            warehouses: [$warehouse],
        );

        self::assertSame('prod-1', $dto->productId);
        self::assertSame('SKU001', $dto->code);
        self::assertSame('Laptop 15"', $dto->name);
        self::assertEqualsWithDelta(10.0, $dto->totalStock, 0.001);
        self::assertCount(1, $dto->warehouses);
        self::assertSame($warehouse, $dto->warehouses[0]);
    }

    public function testProductStockDtoNullCode(): void
    {
        $dto = new ProductStockDto('p-1', null, 'Servicio', 0.0, []);
        self::assertNull($dto->code);
    }

    public function testSalesSummaryDtoHoldsFields(): void
    {
        $dto = new SalesSummaryDto(
            from: '2026-04-01',
            to: '2026-04-30',
            totalSales: 42,
            totalAmount: 15750.00,
            averageTicket: 375.00,
        );

        self::assertSame('2026-04-01', $dto->from);
        self::assertSame('2026-04-30', $dto->to);
        self::assertSame(42, $dto->totalSales);
        self::assertEqualsWithDelta(15750.0, $dto->totalAmount, 0.001);
        self::assertEqualsWithDelta(375.0, $dto->averageTicket, 0.001);
    }

    public function testRecentSaleDtoHoldsFields(): void
    {
        $dto = new RecentSaleDto(
            id: 'sale-1',
            total: 250.00,
            issuedAt: '2026-04-30',
            invoice: null,
            series: 'B001',
            number: '00042',
        );

        self::assertSame('sale-1', $dto->id);
        self::assertEqualsWithDelta(250.0, $dto->total, 0.001);
        self::assertSame('2026-04-30', $dto->issuedAt);
        self::assertNull($dto->invoice);
        self::assertSame('B001', $dto->series);
    }

    public function testProductSearchResultDtoHoldsFields(): void
    {
        $dto = new ProductSearchResultDto(
            id: 'prod-2',
            code: 'SKU002',
            name: 'Monitor 27"',
            salePrice: 1299.00,
            stock: 8.0,
            minStock: 2.0,
        );

        self::assertSame('prod-2', $dto->id);
        self::assertSame('SKU002', $dto->code);
        self::assertSame('Monitor 27"', $dto->name);
        self::assertEqualsWithDelta(1299.0, $dto->salePrice, 0.001);
        self::assertEqualsWithDelta(8.0, $dto->stock ?? 0, 0.001);
        self::assertEqualsWithDelta(2.0, $dto->minStock ?? 0, 0.001);
    }

    // ── BranchContextAdapter ──────────────────────────────────────────────────
    // BranchContextService is final; use a real instance via container is overkill.
    // Test the adapter indirectly: wrap in an anonymous sub-class that overrides the method.

    private function makeAdapterWithBranchId(?string $branchId): BranchContextAdapter
    {
        $service = $this->createStub(BranchContextServiceInterface::class);
        $service->method('getCurrentBranchId')->willReturn($branchId);

        return new BranchContextAdapter($service);
    }

    public function testBranchContextAdapterImplementsInterface(): void
    {
        $adapter = $this->makeAdapterWithBranchId('branch-1');
        self::assertInstanceOf(BranchContextInterface::class, $adapter);
    }

    public function testBranchContextAdapterDelegatesToService(): void
    {
        $adapter = $this->makeAdapterWithBranchId('branch-42');
        self::assertSame('branch-42', $adapter->getCurrentBranchId());
    }

    public function testBranchContextAdapterReturnsNullWhenNoActiveBranch(): void
    {
        $adapter = $this->makeAdapterWithBranchId(null);
        self::assertNull($adapter->getCurrentBranchId());
    }
}
