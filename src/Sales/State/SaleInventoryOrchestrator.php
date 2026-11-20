<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Identity\Dto\AuthUserDto;
use App\Inventory\Core\Contract\SalesInventoryPortInterface;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use Nubit\Platform\Exception\ServiceException;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates stock validation and inventory movements for a sale.
 */
final readonly class SaleInventoryOrchestrator
{
    public function __construct(
        private SalesInventoryPortInterface $salesInventory,
        private StockValidationPolicyInterface $stockValidationPolicy,
        private NegativeStockPolicyResolver $policyResolver,
        private LoggerInterface $logger,
    ) {}

    /**
     * Validates stock availability and records inventory movements for all sale items.
     *
     * When $isOfflineSale is true (sale originated from an offline POS terminal),
     * stock validation is skipped and negative stock is allowed — the physical
     * transaction already occurred and cannot be undone.
     *
     * @throws ServiceException
     * @throws \Doctrine\DBAL\Exception
     */
    public function process(SaleRecord $sale, AuthUserDto $user, bool $isOfflineSale = false): void
    {
        $warehouse = $sale->getAlmacen();
        if ($warehouse === null) {
            return;
        }

        $this->logger->info('Updating warehouse stock', [
            'warehouse_id' => $warehouse->getId(),
            'sale_type_id' => $sale->getTipoId(),
            'is_offline_sale' => $isOfflineSale,
        ]);

        $useCoreValidation = $this->policyResolver->shouldUseCoreValidation();

        // Offline sales bypass stock validation — the transaction is a physical fait accompli.
        if ($isOfflineSale) {
            $this->applyInventoryMovements($sale, $warehouse, $useCoreValidation, false);

            return;
        }

        $policy = $this->policyResolver->resolvePolicy();
        $allowNegative = $this->policyResolver->canSell($policy, $user);
        $enforceNonNegative = $sale->getTipoId() === 2 ? !$allowNegative : true;

        if ($sale->getTipoId() === 2) {
            $this->validateStock($sale, $warehouse, $useCoreValidation, $enforceNonNegative);
        }

        $this->applyInventoryMovements($sale, $warehouse, $useCoreValidation, $enforceNonNegative);
    }

    /**
     * @throws ServiceException
     * @throws \Doctrine\DBAL\Exception
     */
    private function validateStock(
        SaleRecord $sale,
        Warehouse $warehouse,
        bool $useCoreValidation,
        bool $enforceNonNegative,
    ): void {
        /** @var SaleItemRecord $item */
        foreach ($sale->getSaleItemRecords() as $item) {
            if (!$item->getProduct() || !$this->policyResolver->isStockTracked($item->getProduct())) {
                continue;
            }

            $currentStock = $this->salesInventory->getStockForProductInWarehouse($item->getProduct(), $warehouse);

            if ($useCoreValidation) {
                $validation = $this->stockValidationPolicy->validateSale(
                    $item->getProduct(),
                    (string) $warehouse->getId(),
                    number_format((float) $item->getQty(), 6, '.', ''),
                    number_format($currentStock, 6, '.', ''),
                );

                if (!$validation->allowed) {
                    throw new ServiceException(sprintf(
                        'Stock insuficiente para "%s" en el almacén seleccionado. Disponible: %s, solicitado: %s',
                        $item->getProduct()->getName(),
                        rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format((float) $item->getQty(), 3, '.', ''), '0'), '.'),
                    ));
                }

                if ($validation->warning !== null) {
                    $this->logger->warning('Inventory policy warning on sale validation', [
                        'sale_id' => $sale->getId(),
                        'product_id' => $item->getProduct()->getId(),
                        'warehouse_id' => $warehouse->getId(),
                        'warning' => $validation->warning,
                        'available_qty' => $currentStock,
                        'requested_qty' => $item->getQty(),
                    ]);
                }

                continue;
            }

            if ($enforceNonNegative && $currentStock < (float) $item->getQty()) {
                throw new ServiceException(sprintf(
                    'Stock insuficiente para "%s" en el almacén seleccionado. Disponible: %s, solicitado: %s',
                    $item->getProduct()->getName(),
                    rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float) $item->getQty(), 3, '.', ''), '0'), '.'),
                ));
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function applyInventoryMovements(
        SaleRecord $sale,
        Warehouse $warehouse,
        bool $useCoreValidation,
        bool $enforceNonNegative,
    ): void {
        $lineNo = 0;

        /** @var SaleItemRecord $item */
        foreach ($sale->getSaleItemRecords() as $item) {
            ++$lineNo;

            if (!$item->getProduct() || !$this->policyResolver->isStockTracked($item->getProduct())) {
                continue;
            }

            $productId = $item->getProduct()->getId();
            $warehouseId = $warehouse->getId();
            $currentStock = $this->salesInventory->getStockForProductInWarehouse($item->getProduct(), $warehouse);

            $this->logger->debug('Current stock for product', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'current_stock' => $currentStock,
            ]);

            if ($sale->getTipoId() === 1) {
                $this->logger->info('Increasing stock for purchase', [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity_added' => $item->getQty(),
                ]);

                $this->salesInventory->applyMovementFromSaleItem($sale, $item, $lineNo, $enforceNonNegative);
            } elseif ($sale->getTipoId() === 2) {
                $this->logger->info('Decreasing stock for sale', [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity_removed' => $item->getQty(),
                ]);

                $this->salesInventory->applyMovementFromSaleItem(
                    $sale,
                    $item,
                    $lineNo,
                    $useCoreValidation ? false : $enforceNonNegative,
                );
            }
        }
    }
}
