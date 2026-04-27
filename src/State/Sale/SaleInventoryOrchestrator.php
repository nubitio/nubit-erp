<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Exception\ServiceException;
use App\Dto\Auth\AuthUserDto;
use App\Sales\Entity\SaleRecord;
use App\Sales\Entity\SaleItem;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Service\InventoryLedgerService;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates stock validation and ledger writes for a sale.
 */
final readonly class SaleInventoryOrchestrator
{
    public function __construct(
        private InventoryLedgerService $inventoryLedgerService,
        private StockValidationPolicyInterface $stockValidationPolicy,
        private NegativeStockPolicyResolver $policyResolver,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Validates stock availability and records inventory ledger movements for all sale items.
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
        if (!$sale->getAlmacen()) {
            return;
        }

        $this->logger->info('Updating warehouse stock', [
            'warehouse_id' => $sale->getAlmacen()->getId(),
            'sale_type_id' => $sale->getTipoId(),
            'is_offline_sale' => $isOfflineSale,
        ]);

        $useCoreValidation = $this->policyResolver->shouldUseCoreValidation();

        // Offline sales bypass stock validation — the transaction is a physical fait accompli.
        if ($isOfflineSale) {
            $this->applyLedgerMovements($sale, $useCoreValidation, false);

            return;
        }

        $policy = $this->policyResolver->resolvePolicy();
        $allowNegative = $this->policyResolver->canSell($policy, $user);
        $enforceNonNegative = $sale->getTipoId() === 2 ? !$allowNegative : true;

        if ($sale->getTipoId() === 2) {
            $this->validateStock($sale, $useCoreValidation, $enforceNonNegative);
        }

        $this->applyLedgerMovements($sale, $useCoreValidation, $enforceNonNegative);
    }

    /**
     * @throws ServiceException
     * @throws \Doctrine\DBAL\Exception
     */
    private function validateStock(SaleRecord $sale, bool $useCoreValidation, bool $enforceNonNegative): void
    {
        /** @var SaleItem $item */
        foreach ($sale->getItems() as $item) {
            if (!$item->getProducto() || !$this->policyResolver->isStockTracked($item->getProducto())) {
                continue;
            }

            $currentStock = $this->inventoryLedgerService->getStockForProductInWarehouse(
                $item->getProducto(),
                $sale->getAlmacen()
            );

            if ($useCoreValidation) {
                $validation = $this->stockValidationPolicy->validateSale(
                    $item->getProducto(),
                    (string) $sale->getAlmacen()->getId(),
                    number_format((float) $item->getCantidad(), 6, '.', ''),
                    number_format($currentStock, 6, '.', ''),
                );

                if (!$validation->allowed) {
                    throw new ServiceException(sprintf(
                        'Stock insuficiente para "%s" en el almacén seleccionado (%s).'
                        . ' Disponible: %s, solicitado: %s',
                        $item->getProducto()->getName(),
                        $validation->reason ?? 'STRICT',
                        rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format((float) $item->getCantidad(), 3, '.', ''), '0'), '.')
                    ));
                }

                if ($validation->warning !== null) {
                    $this->logger->warning('Inventory policy warning on sale validation', [
                        'sale_id' => $sale->getId(),
                        'product_id' => $item->getProducto()->getId(),
                        'warehouse_id' => $sale->getAlmacen()->getId(),
                        'warning' => $validation->warning,
                        'available_qty' => $currentStock,
                        'requested_qty' => $item->getCantidad(),
                    ]);
                }

                continue;
            }

            if ($enforceNonNegative && $currentStock < $item->getCantidad()) {
                throw new ServiceException(sprintf(
                    'Stock insuficiente para "%s" en el almacén seleccionado. Disponible: %s, solicitado: %s',
                    $item->getProducto()->getName(),
                    rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float) $item->getCantidad(), 3, '.', ''), '0'), '.')
                ));
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function applyLedgerMovements(SaleRecord $sale, bool $useCoreValidation, bool $enforceNonNegative): void
    {
        $lineNo = 0;

        /** @var SaleItem $item */
        foreach ($sale->getItems() as $item) {
            ++$lineNo;
            $item->setDescuento($item->getDescuento());

            if (!$item->getProducto() || !$this->policyResolver->isStockTracked($item->getProducto())) {
                continue;
            }

            $productId = $item->getProducto()->getId();
            $warehouseId = $sale->getAlmacen()->getId();
            $currentStock = $this->inventoryLedgerService->getStockForProductInWarehouse(
                $item->getProducto(),
                $sale->getAlmacen()
            );

            $this->logger->debug('Current stock for product', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'current_stock' => $currentStock,
            ]);

            if ($sale->getTipoId() === 1) {
                $this->logger->info('Increasing stock for purchase', [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity_added' => $item->getCantidad(),
                ]);

                $this->inventoryLedgerService->applyMovementFromSaleItem(
                    $sale,
                    $item,
                    $lineNo,
                    $enforceNonNegative
                );
            } elseif ($sale->getTipoId() === 2) {
                $this->logger->info('Decreasing stock for sale', [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity_removed' => $item->getCantidad(),
                ]);

                $this->inventoryLedgerService->applyMovementFromSaleItem(
                    $sale,
                    $item,
                    $lineNo,
                    $useCoreValidation ? false : $enforceNonNegative
                );
            }
        }
    }
}
