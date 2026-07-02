<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Inventory\Entity\StockLot;
use App\Inventory\Repository\StockLotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ServiceException;

/**
 * Handles FIFO / FEFO lot deduction for inventory movements.
 *
 * All quantity arithmetic uses bcmath (scale 6) — never float — to avoid
 * silent rounding errors on fractional quantities (pharmacies, food, etc.).
 *
 * Returns an ordered list of lot deductions to be recorded as individual
 * stock_movements rows with their respective lot_id.
 */
final readonly class LotDeductionService
{
    private const int SCALE = 6;
    private const string ZERO = '0.000000';
    /** Epsilon for "is remaining zero?" comparisons (avoids bccomp with literal 0). */
    private const string EPSILON = '0.000001';

    public function __construct(
        private StockLotRepository $stockLotRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Deduct $qty from lots using the product's tracking strategy (FIFO / FEFO).
     *
     * @return array<array{lot: StockLot, qty: string}>
     *
     * @throws ServiceException when insufficient stock across all lots
     * @throws \LogicException  when strategy is NONE
     */
    public function deduct(string $productId, string $warehouseId, string $qty, LotTrackingStrategy $strategy): array
    {
        $lots = match ($strategy) {
            LotTrackingStrategy::FEFO => $this->stockLotRepository->findForFefo($productId, $warehouseId),
            LotTrackingStrategy::FIFO => $this->stockLotRepository->findForFifo($productId, $warehouseId),
            LotTrackingStrategy::NONE => throw new \LogicException('Cannot deduct by lot when strategy is NONE.'),
        };

        $remaining = bcadd($qty, self::ZERO, self::SCALE); // normalise scale
        $deductions = [];

        foreach ($lots as $lot) {
            // Stop as soon as remaining ≤ epsilon (handles sub-unit floating point carry)
            if (bccomp($remaining, self::EPSILON, self::SCALE) <= 0) {
                break;
            }

            $available = $lot->getQtyOnHand();
            $take = bccomp($available, $remaining, self::SCALE) >= 0 ? $remaining : $available; // lot has more than enough // take the entire lot

            $lot->setQtyOnHand(bcsub($available, $take, self::SCALE));
            $this->entityManager->persist($lot);

            $deductions[] = ['lot' => $lot, 'qty' => $take];
            $remaining = bcsub($remaining, $take, self::SCALE);
        }

        if (bccomp($remaining, self::EPSILON, self::SCALE) > 0) {
            throw new ServiceException(sprintf(
                'Stock insuficiente en lotes para producto %s (faltan %s unidades).',
                $productId,
                $remaining,
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
        string $qty,
        ?string $cost,
        ?\DateTimeImmutable $expirationDate,
        ?\DateTimeImmutable $manufactureDate,
        ?\DateTimeImmutable $receivedAt,
    ): StockLot {
        $lot = $this->stockLotRepository->findByLotNumber($productId, $warehouseId, $lotNumber);

        if ($lot === null) {
            $product = $this->entityManager->getReference(\App\Catalog\Entity\Product::class, $productId);
            $warehouse = $this->entityManager->getReference(\App\Inventory\Entity\Warehouse::class, $warehouseId);

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

        $lot->setQtyOnHand(bcadd($lot->getQtyOnHand(), $qty, self::SCALE));
        $this->entityManager->persist($lot);

        return $lot;
    }
}
