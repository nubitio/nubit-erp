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

        if (!$data->id && $data->tipo_pago) {
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
        foreach ($data->items as $item) {
            $item->movimiento = $data;
            $subtotal += $item->precio_total;
        }

        $data->subtotal = $subtotal;
        $data->total = $subtotal - ($subtotal * $data->descuento / 100);
        $data->estado = 1;

        // Update warehouse-specific stock
        if ($data->almacen) {
            /** @var SaleItem $item */
            foreach ($data->items as $item) {
                if ($item->producto) {
                    $currentStock = $this->productStockService->getStockForProductInWarehouse(
                        $item->producto,
                        $data->almacen
                    );

                    if ($data->tipo_id === 1) {
                        // Increase stock for purchases (tipo_id = 1)
                        $newStock = $currentStock + $item->cantidad;
                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->producto,
                            $data->almacen,
                            $newStock
                        );
                    } elseif ($data->tipo_id === 2) {
                        // Reduce stock for sales (tipo_id = 2)
                        $newStock = max(0, $currentStock - $item->cantidad);
                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->producto,
                            $data->almacen,
                            $newStock
                        );
                    }
                }
            }
        }

        if (!$data->id) {
            $data->date_created = new \DateTimeImmutable();
        } else {
            $data->date_updated = new \DateTimeImmutable();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
