<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Core\Exception\ServiceException;
use App\Entity\StockLot;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Repository\StockLotRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles FIFO / FEFO lot deduction for inventory movements.
 *
 * Returns an ordered list of lot deductions to be recorded
 * as individual stock_movements with their respective lot_id.
 */
final readonly class LotDeductionService
{
    public function __construct(
        private StockLotRepository $stockLotRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Deduct qty from lots using the product's tracking strategy.
     *
     * @return array<array{lot: StockLot, qty: float}>
     *
     * @throws ServiceException when insufficient stock across all lots
     */
    public function deduct(
        string $productId,
        string $warehouseId,
        float $qty,
        LotTrackingStrategy $strategy,
    ): array {
        $lots = match ($strategy) {
            LotTrackingStrategy::FEFO => $this->stockLotRepository->findForFefo($productId, $warehouseId),
            LotTrackingStrategy::FIFO => $this->stockLotRepository->findForFifo($productId, $warehouseId),
            LotTrackingStrategy::NONE => throw new \LogicException('Cannot deduct by lot when strategy is NONE.'),
        };

        $remaining = $qty;
        $deductions = [];

        foreach ($lots as $lot) {
            if ($remaining <= 0.0000005) {
                break;
            }

            $available = $lot->getQtyOnHand();
            $take = min($available, $remaining);

            $lot->setQtyOnHand($available - $take);
            $this->entityManager->persist($lot);

            $deductions[] = ['lot' => $lot, 'qty' => $take];
            $remaining = round($remaining - $take, 6);
        }

        if ($remaining > 0.0000005) {
            throw new ServiceException(sprintf(
                'Stock insuficiente en lotes para producto %s (faltan %.3f unidades).',
                $productId,
                $remaining
            ));
        }

        return $deductions;
    }

    /**
     * Add incoming stock to an existing lot or create it if it does not exist.
     *
     * @throws ServiceException
     */
    public function receive(
        string $productId,
        string $warehouseId,
        string $lotNumber,
        float $qty,
        ?float $cost,
        ?\DateTimeImmutable $expirationDate,
        ?\DateTimeImmutable $manufactureDate,
        ?\DateTimeImmutable $receivedAt,
    ): StockLot {
        $lot = $this->stockLotRepository->findByLotNumber($productId, $warehouseId, $lotNumber);

        if ($lot === null) {
            $product = $this->entityManager->getReference(\App\Entity\Product::class, $productId);
            $warehouse = $this->entityManager->getReference(\App\Entity\Warehouse::class, $warehouseId);

            $lot = new StockLot();
            $lot->setProduct($product);
            $lot->setWarehouse($warehouse);
            $lot->setLotNumber($lotNumber);
            $lot->setExpirationDate($expirationDate);
            $lot->setManufactureDate($manufactureDate);
            $lot->setCost($cost);

            if ($receivedAt !== null) {
                $lot->setReceivedAt($receivedAt);
            }
        }

        $lot->setQtyOnHand($lot->getQtyOnHand() + $qty);
        $this->entityManager->persist($lot);

        return $lot;
    }
}
