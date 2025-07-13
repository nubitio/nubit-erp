<?php

namespace App\State\Sale;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Domain\Exception\ServiceException;
use App\Entity\CashShift;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Enum\CashShiftStatus;
use App\Service\ProductStockService;
use Doctrine\ORM\EntityManagerInterface;
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
        if ($operation instanceof DeleteOperationInterface) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (!$data->getId() && $data->getTipoPago()) {
            $user = $this->security->connectedUser();
            $cashShift = $this->entityManager->getRepository(CashShift::class)
                ->findOneBy([
                    'status' => CashShiftStatus::OPEN,
                    'openedBy' => $user->userId,
                ]);

            if (!$cashShift) {
                throw new ServiceException('No puedes realizar ventas, no tienes una caja abierta');
            }
            $data = $this->saleUtils->fillSaleValues($data);
        }

        $subtotal = 0;
        /** @var SaleItem $item */
        foreach ($data->getItems() as $item) {
            $item->setMovimiento($data);
            $subtotal += $item->getPrecioTotal();
        }

        $data->setSubtotal($subtotal);
        $data->setTotal($subtotal - ($subtotal * $data->getDescuento() / 100));
        $data->setEstado(1);

        // Update warehouse-specific stock
        if ($data->getAlmacen()) {
            /** @var SaleItem $item */
            foreach ($data->getItems() as $item) {
                if ($item->getProducto()) {
                    $currentStock = $this->productStockService->getStockForProductInWarehouse(
                        $item->getProducto(),
                        $data->getAlmacen()
                    );

                    if ($data->getTipoId() === 1) {
                        // Increase stock for purchases (tipo_id = 1)
                        $newStock = $currentStock + $item->getCantidad();
                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->getProducto(),
                            $data->getAlmacen(),
                            $newStock
                        );
                    } elseif ($data->getTipoId() === 2) {
                        // Reduce stock for sales (tipo_id = 2)
                        $newStock = max(0, $currentStock - $item->getCantidad());
                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->getProducto(),
                            $data->getAlmacen(),
                            $newStock
                        );
                    }
                }
            }
        }

        if (!$data->getId()) {
            $data->setDateCreated(new \DateTimeImmutable());
        } else {
            $data->setDateUpdated(new \DateTimeImmutable());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
