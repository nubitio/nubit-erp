<?php

namespace App\State\Sale;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Exception\ServiceException;
use App\Entity\CashShift;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Enum\CashShiftStatus;
use App\Service\ProductStockService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class SaleProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private SaleUtils $saleUtils,
        private ProductStockService $productStockService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param Sale $data
     *
     * @throws ServiceException
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->logger->info('Starting sale processing', [
            'sale_id' => $data->getId(),
            'operation_type' => $operation::class,
            'context' => array_keys($context)
        ]);

        if ($operation instanceof DeleteOperationInterface) {
            $this->logger->info('Processing sale deletion', [
                'sale_id' => $data->getId()
            ]);
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->connectedUser();

        if (!$data->getId() && $data->getTipoPago()) {
            $this->logger->info('Processing new sale with payment method', [
                'payment_method' => $data->getTipoPago()->getId()
            ]);

            $this->logger->debug('Checking for open cash shift', [
                'user_id' => $user->userId
            ]);

            $cashShift = $this->entityManager->getRepository(CashShift::class)
                ->findOneBy([
                    'status' => CashShiftStatus::OPEN,
                    'openedBy' => $user->userId,
                ]);

            if (!$cashShift) {
                $this->logger->warning('Sale processing failed: No open cash shift found', [
                    'user_id' => $user->userId
                ]);
                throw new ServiceException('No puedes realizar ventas, no tienes una caja abierta');
            }

            $this->logger->debug('Cash shift found, filling sale values', [
                'cash_shift_id' => $cashShift->getId()
            ]);

            $data = $this->saleUtils->fillSaleValues($data);
            $this->logger->debug('Sale values filled');
        }

        $this->logger->info('Calculating sale totals');
        $subtotal = 0;
        $itemCount = 0;

        /** @var SaleItem $item */
        foreach ($data->getItems() as $item) {
            $itemCount++;
            $item->setMovimiento($data);
            $subtotal += $item->getPrecioTotal();

            $this->logger->debug('Processed sale item', [
                'item_number' => $itemCount,
                'product_id' => $item->getProducto()?->getId(),
                'quantity' => $item->getCantidad(),
                'price' => $item->getPrecioUnitario(),
                'total' => $item->getPrecioTotal()
            ]);
        }

        $data->setSubtotal($subtotal);
        $total = $subtotal - ($subtotal * $data->getDescuento() / 100);
        $data->setTotal($total);
        $data->setEstado(1);

        $this->logger->info('Sale totals calculated', [
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'discount_percentage' => $data->getDescuento(),
            'total' => $total
        ]);

        // Update warehouse-specific stock
        if ($data->getAlmacen()) {
            $this->logger->info('Updating warehouse stock', [
                'warehouse_id' => $data->getAlmacen()->getId(),
                'sale_type_id' => $data->getTipoId()
            ]);

            /** @var SaleItem $item */
            foreach ($data->getItems() as $item) {
                if ($item->getProducto()) {
                    $productId = $item->getProducto()->getId();
                    $warehouseId = $data->getAlmacen()->getId();

                    $currentStock = $this->productStockService->getStockForProductInWarehouse(
                        $item->getProducto(),
                        $data->getAlmacen()
                    );

                    $this->logger->debug('Current stock for product', [
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'current_stock' => $currentStock
                    ]);

                    if ($data->getTipoId() === 1) {
                        // Increase stock for purchases (tipo_id = 1)
                        $newStock = $currentStock + $item->getCantidad();
                        $this->logger->info('Increasing stock for purchase', [
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'current_stock' => $currentStock,
                            'quantity_added' => $item->getCantidad(),
                            'new_stock' => $newStock
                        ]);

                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->getProducto(),
                            $data->getAlmacen(),
                            $newStock
                        );
                    } elseif ($data->getTipoId() === 2) {
                        // Reduce stock for sales (tipo_id = 2)
                        $newStock = max(0, $currentStock - $item->getCantidad());
                        $this->logger->info('Decreasing stock for sale', [
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'current_stock' => $currentStock,
                            'quantity_removed' => $item->getCantidad(),
                            'new_stock' => $newStock
                        ]);

                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->getProducto(),
                            $data->getAlmacen(),
                            $newStock
                        );
                    }
                }
                $item->setDescuento($item->getDescuento() ?? 0);
            }
        }

        if (!$data->getId()) {
            $this->logger->info('Creating new sale record with timestamp');
            $data->setDateCreated(new \DateTimeImmutable());
            $data->setCreatedBy($user->userId);
        } else {
            $this->logger->info('Updating existing sale record', [
                'sale_id' => $data->getId()
            ]);
            $data->setDateUpdated(new \DateTimeImmutable());
            $data->setUpdatedBy($user->userId);
        }

        $this->logger->info('Persisting sale data', [
            'sale_id' => $data->getId(),
            'customer_id' => $data->getCliente()?->getRuc(),
            'total' => $data->getTotal(),
            'item_count' => count($data->getItems())
        ]);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->logger->info('Sale processing completed successfully', [
            'sale_id' => $data->getId()
        ]);

        return $result;
    }
}
