<?php

declare(strict_types=1);

namespace App\State\Sale;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Core\Util\Money;
use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashShift;
use App\Entity\Customer;
use App\Entity\PaymentMethod;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Enum\CashShiftStatus;
use App\Message\SendInvoiceMessage;
use App\Repository\BranchRepository;
use App\Repository\CashMovementCategoryRepository;
use App\Repository\SunatCatalogRepository;
use App\EInvoicing\Service\InvoiceCreationService;
use App\Service\ProductStockService;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

readonly class SaleProcessor implements ProcessorInterface
{
    private const string DETRACTION_PAYMENT_METHOD = '001';
    private const float MIN_DETRACTION_TOTAL = 700.0;
    private const array DETRACTION_NO_MIN_CODES = [
        '008',
        '016',
        '019',
        '021',
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private SaleUtils $saleUtils,
        private ProductStockService $productStockService,
        private SettingsService $settings,
        private InvoiceCreationService $invoiceCreationService,
        private LoggerInterface $logger,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
        private SunatCatalogRepository $sunatCatalogRepository,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private MessageBusInterface $messageBus,
        private SalesWritePolicyResolver $salesWritePolicyResolver,
        private SalesWriteGateway $salesWriteGateway,
        private CashSessionResolver $cashSessionResolver,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param Sale $data
     *
     * @throws ServiceException
     * @throws Exception
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->logger->info('Starting sale processing', [
            'sale_id' => $data->getId(),
            'operation_type' => $operation::class,
            'context' => array_keys($context),
        ]);

        $isNew = !$data->getId();
        if ($operation instanceof DeleteOperationInterface) {
            $this->logger->info('Processing sale deletion', [
                'sale_id' => $data->getId(),
            ]);

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->connectedUser();
        $cashShiftForMovement = null;
        $isOperationalSale = $data->getTipoId() === 2;
        $salesWritePolicy = $isOperationalSale ? $this->salesWritePolicyResolver->resolve() : null;

        if ($isNew && $data->getTipoPago()) {
            $this->logger->info('Processing new sale with payment method', [
                'payment_method' => $data->getTipoPago()->getId(),
            ]);

            $requireCashShift = filter_var(
                $this->settings->get(Constants::REQUIRE_CASH_REGISTER, 'true'),
                FILTER_VALIDATE_BOOLEAN
            );

            $this->logger->debug('Checking for open cash shift', [
                'user_id' => $user->userId,
            ]);

            $cashShift = $this->entityManager->getRepository(CashShift::class)
                ->findOneBy([
                    'status' => CashShiftStatus::OPEN,
                    'openedBy' => $user->userId,
                ]);

            if ($requireCashShift && !$cashShift instanceof CashShift) {
                $this->logger->warning('Sale processing failed: No open cash shift found', [
                    'user_id' => $user->userId,
                ]);
                throw new ServiceException('No puedes realizar ventas, no tienes una caja abierta');
            }

            if ($cashShift instanceof CashShift) {
                $cashShiftForMovement = $cashShift;
            }

            $data = $this->saleUtils->fillSaleValues($data);
            $this->ensureSaleCustomer($data);
            $this->logger->debug('Sale values filled');
        }

        $this->logger->info('Calculating sale totals');
        $subtotal = BigDecimal::zero();
        $itemCount = 0;

        /** @var SaleItem $item */
        foreach ($data->getItems() as $item) {
            $itemCount++;
            $item->setMovimiento($data);
            $subtotal = $subtotal->plus(Money::money($item->getPrecioTotal() ?? '0'));

            $this->logger->debug('Processed sale item', [
                'item_number' => $itemCount,
                'product_id' => $item->getProducto()?->getId(),
                'quantity' => $item->getCantidad(),
                'price' => $item->getPrecioUnitario(),
                'total' => $item->getPrecioTotal(),
            ]);
        }

        $data->setSubtotal(Money::toString($subtotal));
        $discountRate = Money::rate($data->getDescuento());
        $discountAmount = Money::money($subtotal->multipliedBy($discountRate));
        $total = Money::money($subtotal->minus($discountAmount));
        $data->setTotal(Money::toString($total));
        $data->setEstado(1);
        $this->applyDetractionFromItems($data);

        $this->logger->info('Sale totals calculated', [
            'item_count' => $itemCount,
            'subtotal' => Money::toString($subtotal),
            'discount_percentage' => $data->getDescuento(),
            'total' => Money::toString($total),
        ]);

        if ($isNew) {
            // `has_invoice` reflects the actual emitted document, not a client intent flag.
            $data->setHasInvoice(false);
        }

        // Update warehouse-specific stock
        if ($data->getAlmacen()) {
            $this->logger->info('Updating warehouse stock', [
                'warehouse_id' => $data->getAlmacen()->getId(),
                'sale_type_id' => $data->getTipoId(),
            ]);
            $checkStockEnabled = filter_var(
                $this->settings->get(Constants::CHECK_STOCK, 'true'),
                FILTER_VALIDATE_BOOLEAN
            );

            if ($data->getTipoId() === 2 && $checkStockEnabled) {
                /** @var SaleItem $item */
                foreach ($data->getItems() as $item) {
                    if (!$item->getProducto()) {
                        continue;
                    }

                    $currentStock = $this->productStockService->getStockForProductInWarehouse(
                        $item->getProducto(),
                        $data->getAlmacen()
                    );

                    if ($currentStock < $item->getCantidad()) {
                        throw new ServiceException(sprintf(
                            'Stock insuficiente para "%s" en el almacén seleccionado. Disponible: %s, solicitado: %s',
                            $item->getProducto()->getName(),
                            rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.'),
                            rtrim(rtrim(number_format((float) $item->getCantidad(), 3, '.', ''), '0'), '.')
                        ));
                    }
                }
            }

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
                        'current_stock' => $currentStock,
                    ]);

                    if ($data->getTipoId() === 1) {
                        // Increase stock for purchases (tipo_id = 1)
                        $newStock = $currentStock + $item->getCantidad();
                        $this->logger->info('Increasing stock for purchase', [
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'current_stock' => $currentStock,
                            'quantity_added' => $item->getCantidad(),
                            'new_stock' => $newStock,
                        ]);

                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->getProducto(),
                            $data->getAlmacen(),
                            $newStock
                        );
                    } elseif ($data->getTipoId() === 2) {
                        // Reduce stock for sales (tipo_id = 2)
                        $newStock = $currentStock - $item->getCantidad();
                        $this->logger->info('Decreasing stock for sale', [
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'current_stock' => $currentStock,
                            'quantity_removed' => $item->getCantidad(),
                            'new_stock' => $newStock,
                        ]);

                        $this->productStockService->updateStockForProductInWarehouse(
                            $item->getProducto(),
                            $data->getAlmacen(),
                            $newStock
                        );
                    }
                }

                $item->setDescuento($item->getDescuento());
            }
        }

        if ($isNew) {
            $this->logger->info('Creating new sale record with timestamp');
            $data->setDateCreated(new DateTimeImmutable());
            $data->setCreatedBy($user->userId);

            // Set the branch from the current user's session
            $branchId = $this->branchContextService->getCurrentBranchId();
            if ($branchId !== null) {
                $branch = $this->branchRepository->find($branchId);
                $data->setBranch($branch);
                $this->logger->debug('Branch set for sale', ['branch_id' => $branchId]);
            }
        } else {
            $this->logger->info('Updating existing sale record', [
                'sale_id' => $data->getId(),
            ]);
            $data->setDateUpdated(new DateTimeImmutable());
            $data->setUpdatedBy($user->userId);
        }

        if ($isOperationalSale) {
            if (
                $isNew
                && $salesWritePolicy?->mode === SalesWriteMode::AUTHORITATIVE
                && $data->getId() === null
            ) {
                // Decouple new-schema identity from legacy movimiento id generation.
                $data->setId(Uuid::v7()->toRfc4122());
            }
        }

        $this->logger->info('Persisting sale data', [
            'sale_id' => $data->getId(),
            'customer_id' => $data->getCliente()?->getId(),
            'total' => $data->getTotal(),
            'item_count' => count($data->getItems()),
        ]);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($isOperationalSale) {
            if (!$salesWritePolicy instanceof SalesWritePolicy) {
                throw new \LogicException('Sales write policy must be resolved for operational sale flow.');
            }

            $this->logger->info('Sales V2 runtime mode resolved', [
                'sale_id' => $data->getId(),
                'mode' => $salesWritePolicy->mode,
                'write_new_schema' => $salesWritePolicy->writeNewSchema,
                'strict' => $salesWritePolicy->strict,
            ]);

            if ($salesWritePolicy->writeNewSchema) {
                try {
                    $cashSessionId = $this->cashSessionResolver->resolveForSale(
                        $data,
                        $cashShiftForMovement?->getId()
                    );
                    $this->salesWriteGateway->write($data, $cashSessionId);
                } catch (\Throwable $exception) {
                    $this->logger->error('Sales V2 dual-write failed', [
                        'sale_id' => $data->getId(),
                        'mode' => $salesWritePolicy->mode,
                        'strict' => $salesWritePolicy->strict,
                        'error' => $exception->getMessage(),
                    ]);

                    if ($salesWritePolicy->strict) {
                        throw new ServiceException(
                            'No se pudo sincronizar la venta con Sales V2. Operación cancelada.'
                        );
                    }
                }
            }
        }

        if (
            $isNew && $data->getTipoPago()?->getId() === PaymentMethod::CASH &&
            $cashShiftForMovement instanceof CashShift
        ) {
            $this->createCashMovementForSale($data, $cashShiftForMovement);
        }

        $autoInvoiceEnabled = filter_var(
            $this->settings->get(Constants::AUTO_INVOICE, 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        $emitInvoice = $data->getEmitInvoice();
        $shouldEmitInvoice = $emitInvoice !== null ? $emitInvoice : $autoInvoiceEnabled;
        if ($isNew && $data->getTipoId() === 2 && $shouldEmitInvoice) {
            try {
                $invoiceResult = $this->invoiceCreationService->createForSale($data);
                if (!$invoiceResult['success']) {
                    $this->logger->warning('Auto invoice skipped', [
                        'sale_id' => $data->getId(),
                        'message' => $invoiceResult['message'],
                    ]);
                } else {
                    $this->messageBus->dispatch(new SendInvoiceMessage($invoiceResult['invoiceId']));
                }
            } catch (\Throwable $throwable) {
                $this->logger->error('Auto invoice failed', [
                    'sale_id' => $data->getId(),
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $this->logger->info('Sale processing completed successfully', [
            'sale_id' => $data->getId(),
        ]);

        return $result;
    }

    private function applyDetractionFromItems(Sale $sale): void
    {
        $customerDoc = $sale->getCliente()?->getRuc() ?? '';
        if (strlen($customerDoc) !== 11) {
            $sale->setDetraccionCodigo(null);
            $sale->setDetraccionPorcentaje(null);
            $sale->setDetraccionMonto(null);
            $sale->setDetraccionMedioPago(null);
            $sale->setDetraccionValorReferencial(null);
            if ($sale->getTipoOperacion() === '1001') {
                $sale->setTipoOperacion(null);
            }

            return;
        }

        $detractionCatalog = null;

        foreach ($sale->getItems() as $item) {
            $catalog = $item->getProducto()?->getDetractionCatalog();
            if (!$catalog) {
                continue;
            }

            if ($detractionCatalog && $catalog->getId() !== $detractionCatalog->getId()) {
                throw new ServiceException(
                    'Los productos tienen diferentes códigos de detracción. Separe la venta en comprobantes distintos.'
                );
            }

            $detractionCatalog = $catalog;
        }

        if (!$detractionCatalog) {
            $sale->setDetraccionCodigo(null);
            $sale->setDetraccionPorcentaje(null);
            $sale->setDetraccionMonto(null);
            $sale->setDetraccionMedioPago(null);
            $sale->setDetraccionValorReferencial(null);
            if ($sale->getTipoOperacion() === '1001') {
                $sale->setTipoOperacion(null);
            }

            return;
        }

        $total = (float)($sale->getTotal() ?? 0);
        $detractionCode = $detractionCatalog->getCode();
        $threshold = $this->getDetractionThreshold($detractionCode);

        if ($threshold > 0 && $total <= $threshold) {
            $sale->setDetraccionCodigo(null);
            $sale->setDetraccionPorcentaje(null);
            $sale->setDetraccionMonto(null);
            $sale->setDetraccionMedioPago(null);
            $sale->setDetraccionValorReferencial(null);
            if ($sale->getTipoOperacion() === '1001') {
                $sale->setTipoOperacion(null);
            }

            return;
        }

        $sale->setDetraccionCodigo($detractionCatalog->getCode());
        $sale->setDetraccionPorcentaje($detractionCatalog->getPercentage());
        $amount = $sale->getDetraccionPorcentaje() !== null
            ? round($sale->getTotal() * $sale->getDetraccionPorcentaje() / 100, 2)
            : null;
        $sale->setDetraccionMonto($amount);

        $paymentMethod = $this->sunatCatalogRepository->findOneBy([
            'catalog' => 59,
            'code' => self::DETRACTION_PAYMENT_METHOD,
            'active' => true,
        ]);
        if (!$paymentMethod) {
            throw new ServiceException('No se encontró el medio de pago para detracción (catálogo 59).');
        }
        $sale->setDetraccionMedioPago(self::DETRACTION_PAYMENT_METHOD);

        if ($sale->getTipoOperacion() === null) {
            $sale->setTipoOperacion('1001');
        }
    }

    private function ensureSaleCustomer(Sale $sale): void
    {
        if ($sale->getTipoId() !== 2 || $sale->getCliente() instanceof Customer) {
            return;
        }

        $defaultCustomer = $this->entityManager->find(Customer::class, 1);
        if (!$defaultCustomer instanceof Customer) {
            throw new ServiceException(
                'Debe seleccionar un cliente para la venta y no existe el cliente general (ID 1).'
            );
        }

        $sale->setCliente($defaultCustomer);
    }

    private function getDetractionThreshold(string $code): float
    {
        if (in_array($code, self::DETRACTION_NO_MIN_CODES, true)) {
            return 0.0;
        }

        return self::MIN_DETRACTION_TOTAL;
    }

    private function createCashMovementForSale(Sale $sale, CashShift $cashShift): void
    {
        $category = $this->cashMovementCategoryRepository->findOneBy([
            'code' => CashMovementCategory::CASH_SALE,
        ]);

        if (!$category instanceof CashMovementCategory) {
            throw new ServiceException('No se encontró la categoría de venta en efectivo.');
        }

        $movement = new CashMovement();
        $movement->setCashShift($cashShift);
        $movement->setCategory($category);
        $movement->setType($category->getType());
        $movement->setAmount($sale->getTotal() ?? 0);
        $movement->setDescription('Venta en efectivo N° ' . $sale->getserie() . '-' . $sale->getNumero());
        $movement->setCreatedAt(new DateTimeImmutable());
        $movement->setBranch($sale->getBranch());

        $this->entityManager->persist($movement);
        $this->entityManager->flush();
    }
}
