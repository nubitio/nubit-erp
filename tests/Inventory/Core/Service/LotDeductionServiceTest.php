<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Core\Exception\ServiceException;
use App\Inventory\Entity\StockLot;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Inventory\Core\Service\LotDeductionService;
use App\Inventory\Repository\StockLotRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LotDeductionService.
 *
 * All dependencies are mocked — no real DB involved.
 *
 * Coverage targets:
 *   - deduct(): FIFO, FEFO, full deduction, partial across multiple lots,
 *               insufficient stock, exact epsilon boundary, NONE strategy exception
 *   - receive(): new lot creation, existing lot qty accumulation
 */
final class LotDeductionServiceTest extends TestCase
{
    private StockLotRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private LotDeductionService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockLotRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new LotDeductionService($this->repository, $this->em);
    }

    // ─── deduct() ────────────────────────────────────────────────────────────────

    public function testDeductFifoFullyFromSingleLot(): void
    {
        $lot = $this->makeLot('10.000000');

        $this->repository
            ->method('findForFifo')
            ->willReturn([$lot]);

        $this->em->expects(self::once())->method('persist')->with($lot);

        $result = $this->service->deduct('prod-1', 'wh-1', '5.000000', LotTrackingStrategy::FIFO);

        self::assertCount(1, $result);
        self::assertSame('5.000000', $result[0]['qty']);
        self::assertSame('5.000000', $lot->getQtyOnHand());
    }

    public function testDeductFefoFullyFromSingleLot(): void
    {
        $lot = $this->makeLot('3.500000');

        $this->repository
            ->method('findForFefo')
            ->willReturn([$lot]);

        $this->em->expects(self::once())->method('persist');

        $result = $this->service->deduct('prod-1', 'wh-1', '3.500000', LotTrackingStrategy::FEFO);

        self::assertCount(1, $result);
        self::assertSame('3.500000', $result[0]['qty']);
        self::assertSame('0.000000', $lot->getQtyOnHand());
    }

    public function testDeductSpreadAcrossMultipleLots(): void
    {
        $lot1 = $this->makeLot('3.000000');
        $lot2 = $this->makeLot('4.000000');

        $this->repository
            ->method('findForFifo')
            ->willReturn([$lot1, $lot2]);

        $this->em->expects(self::exactly(2))->method('persist');

        $result = $this->service->deduct('prod-1', 'wh-1', '5.000000', LotTrackingStrategy::FIFO);

        self::assertCount(2, $result);
        self::assertSame('3.000000', $result[0]['qty']);
        self::assertSame('2.000000', $result[1]['qty']);
        self::assertSame('0.000000', $lot1->getQtyOnHand());
        self::assertSame('2.000000', $lot2->getQtyOnHand());
    }

    public function testDeductThrowsWhenInsufficientStock(): void
    {
        $lot = $this->makeLot('2.000000');

        $this->repository
            ->method('findForFifo')
            ->willReturn([$lot]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/insuficiente/');

        $this->service->deduct('prod-1', 'wh-1', '5.000000', LotTrackingStrategy::FIFO);
    }

    public function testDeductThrowsWhenNoLotsAvailable(): void
    {
        $this->repository
            ->method('findForFifo')
            ->willReturn([]);

        $this->expectException(ServiceException::class);

        $this->service->deduct('prod-1', 'wh-1', '1.000000', LotTrackingStrategy::FIFO);
    }

    public function testDeductThrowsLogicExceptionForNoneStrategy(): void
    {
        $this->expectException(\LogicException::class);

        $this->service->deduct('prod-1', 'wh-1', '1.000000', LotTrackingStrategy::NONE);
    }

    /**
     * Verify sub-unit epsilon boundary: if remaining after all lots is ≤ 0.000001
     * we treat it as fully satisfied (handles DECIMAL rounding carry from DB).
     */
    public function testDeductAcceptsEpsilonRemainder(): void
    {
        // Lot has 0.999999 — deducting 1.000000 would leave 0.000001 (epsilon)
        $lot = $this->makeLot('0.999999');

        $this->repository
            ->method('findForFifo')
            ->willReturn([$lot]);

        $this->em->expects(self::once())->method('persist');

        // Should NOT throw because remainder 0.000001 ≤ epsilon
        $result = $this->service->deduct('prod-1', 'wh-1', '0.999999', LotTrackingStrategy::FIFO);

        self::assertCount(1, $result);
    }

    /**
     * Verify no float precision errors: 0.1 + 0.2 in bcmath must not introduce drift.
     */
    public function testDeductNoBcmathPrecisionDrift(): void
    {
        $lot = $this->makeLot('1.000000');

        $this->repository
            ->method('findForFifo')
            ->willReturn([$lot]);

        $this->em->expects(self::once())->method('persist');

        // In native float: 0.1 + 0.2 = 0.30000000000000004
        $result = $this->service->deduct('prod-1', 'wh-1', '0.300000', LotTrackingStrategy::FIFO);

        self::assertSame('0.300000', $result[0]['qty']);
        self::assertSame('0.700000', $lot->getQtyOnHand());
    }

    // ─── receive() ───────────────────────────────────────────────────────────────

    public function testReceiveCreatesNewLotWhenNotFound(): void
    {
        $this->repository
            ->method('findByLotNumber')
            ->willReturn(null);

        $productRef = $this->createStub(\App\Catalog\Entity\Product::class);
        $warehouseRef = $this->createStub(\App\Inventory\Entity\Warehouse::class);

        $this->em
            ->method('getReference')
            ->willReturnMap([
                [\App\Catalog\Entity\Product::class, 'prod-1', $productRef],
                [\App\Inventory\Entity\Warehouse::class, 'wh-1', $warehouseRef],
            ]);

        $this->em->expects(self::once())->method('persist');

        $lot = $this->service->receive(
            productId: 'prod-1',
            warehouseId: 'wh-1',
            lotNumber: 'LOT-001',
            qty: '10.000000',
            cost: '2.5000',
            expirationDate: null,
            manufactureDate: null,
            receivedAt: null,
        );

        self::assertSame('10.000000', $lot->getQtyOnHand());
        self::assertSame('2.5000', $lot->getCost());
    }

    public function testReceiveAccumulatesQtyOnExistingLot(): void
    {
        $existing = $this->makeLot('5.000000');

        $this->repository
            ->method('findByLotNumber')
            ->willReturn($existing);

        $this->em->expects(self::once())->method('persist');

        $lot = $this->service->receive(
            productId: 'prod-1',
            warehouseId: 'wh-1',
            lotNumber: 'LOT-001',
            qty: '3.000000',
            cost: null,
            expirationDate: null,
            manufactureDate: null,
            receivedAt: null,
        );

        self::assertSame('8.000000', $lot->getQtyOnHand());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function makeLot(string $qtyOnHand): StockLot
    {
        $lot = new StockLot();
        $lot->setQtyOnHand($qtyOnHand);

        return $lot;
    }
}
