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
use App\Entity\CashShift;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Enum\CashShiftStatus;
use App\Message\SendInvoiceMessage;
use App\Repository\BranchRepository;
use App\Repository\SunatCatalogRepository;
use App\EInvoicing\Service\InvoiceCreationService;
use App\Service\InventoryLedgerService;
use App\Service\SalePaymentPlanService;
use App\State\Purchase\GoodsReceiptWriteGateway;
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
    private const string NEGATIVE_STOCK_BLOCK = 'BLOCK';
    private const string NEGATIVE_STOCK_ALLOW_WITH_ROLE = 'ALLOW_WITH_ROLE';
    private const string NEGATIVE_STOCK_ALLOW_ALL = 'ALLOW_ALL';
    private const string NEGATIVE_STOCK_STRICT = 'STRICT';
    private const string NEGATIVE_STOCK_WARNING_ONLY = 'WARNING_ONLY';
    private const string NEGATIVE_STOCK_ALLOW_NEGATIVE = 'ALLOW_NEGATIVE';
    private const string PURCHASES_MODE_LEGACY = 'legacy';

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
        private InventoryLedgerService $inventoryLedgerService,
        private SettingsService $settings,
        private InvoiceCreationService $invoiceCreationService,
        private LoggerInterface $logger,
        private SunatCatalogRepository $sunatCatalogRepository,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private MessageBusInterface $messageBus,
        private SalesWritePolicyResolver $salesWritePolicyResolver,
        private SalesWriteGateway $salesWriteGateway,
        private GoodsReceiptWriteGateway $goodsReceiptWriteGateway,
        private CashSessionResolver $cashSessionResolver,
        private SalePaymentPlanService $salePaymentPlanService,
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

        // Ensure deterministic movement source keys before inventory ledger writes.
        if ($isNew && $data->getId() === null) {
            $data->setId(Uuid::v7()->toRfc4122());
        }

        $user = $this->security->connectedUser();
        $cashShiftForMovement = null;
        $isOperationalSale = $data->getTipoId() === 2;
        $salesWritePolicy = $isOperationalSale ? $this->salesWritePolicyResolver->resolve() : null;
        $resolvedCashSessionId = null;

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
        if ($isOperationalSale) {
            $this->salePaymentPlanService->resolveForWrite(
                $data,
                strtoupper(trim((string) ($data->getCurrency() ?? 'PEN')) ?: 'PEN'),
                $data->getTotal() ?? '0.00'
            );
        }

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
            $negativeStockPolicy = $this->resolveNegativeStockPolicy();
            $allowNegativeStock = $this->canSellWithNegativeStock($negativeStockPolicy, $user);
            $enforceNonNegativeStock = $data->getTipoId() === 2 ? !$allowNegativeStock : true;

            if ($data->getTipoId() === 2 && $enforceNonNegativeStock) {
                /** @var SaleItem $item */
                foreach ($data->getItems() as $item) {
                    if (!$item->getProducto() || !$this->isStockTrackedProduct($item->getProducto())) {
                        continue;
                    }

                    $currentStock = $this->inventoryLedgerService->getStockForProductInWarehouse(
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

            $lineNo = 0;
            /** @var SaleItem $item */
            foreach ($data->getItems() as $item) {
                ++$lineNo;
                if ($item->getProducto()) {
                    if (!$this->isStockTrackedProduct($item->getProducto())) {
                        continue;
                    }

                    $productId = $item->getProducto()->getId();
                    $warehouseId = $data->getAlmacen()->getId();

                    $currentStock = $this->inventoryLedgerService->getStockForProductInWarehouse(
                        $item->getProducto(),
                        $data->getAlmacen()
                    );

                    $this->logger->debug('Current stock for product', [
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'current_stock' => $currentStock,
                    ]);

                    if ($data->getTipoId() === 1) {
                        $this->logger->info('Increasing stock for purchase', [
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'current_stock' => $currentStock,
                            'quantity_added' => $item->getCantidad(),
                        ]);

                        $this->inventoryLedgerService->applyMovementFromSaleItem(
                            $data,
                            $item,
                            $lineNo,
                            $enforceNonNegativeStock
                        );
                    } elseif ($data->getTipoId() === 2) {
                        $this->logger->info('Decreasing stock for sale', [
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'current_stock' => $currentStock,
                            'quantity_removed' => $item->getCantidad(),
                        ]);

                        $this->inventoryLedgerService->applyMovementFromSaleItem(
                            $data,
                            $item,
                            $lineNo,
                            $enforceNonNegativeStock
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

        $this->logger->info('Persisting sale data', [
            'sale_id' => $data->getId(),
            'customer_id' => $data->getCliente()?->getId(),
            'total' => $data->getTotal(),
            'item_count' => count($data->getItems()),
        ]);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($data->getTipoId() === 1 && $this->isPurchasesV2WriteEnabled()) {
            try {
                $this->goodsReceiptWriteGateway->write($data);
            } catch (\Throwable $exception) {
                $this->logger->error('Goods receipts V2 dual-write failed', [
                    'movimiento_id' => $data->getId(),
                    'error' => $exception->getMessage(),
                ]);

                if ($this->isPurchasesV2StrictMode()) {
                    throw new ServiceException(
                        'No se pudo sincronizar la compra con Compras V2. Operación cancelada.'
                    );
                }
            }
        }

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
                    $resolvedCashSessionId = $cashSessionId;
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

        if ($isNew && $isOperationalSale) {
            $cashCollected = $this->salePaymentPlanService->resolveCashCollectedAmount(
                $data,
                $data->getTotal() ?? '0.00'
            );
            if (Money::money($cashCollected)->isGreaterThan(BigDecimal::zero())) {
                if ($resolvedCashSessionId !== null) {
                    try {
                        $this->salesWriteGateway->writeCashSaleMovement(
                            saleId: (string) $data->getId(),
                            cashSessionId: $resolvedCashSessionId,
                            createdBy: $user->userId,
                            amount: $cashCollected,
                            categoryId: null,
                            paymentMethodId: \App\Entity\PaymentMethod::CASH,
                            createdAt: $data->getFecha() ?? new DateTimeImmutable()
                        );
                    } catch (\Throwable $exception) {
                        $this->logger->error('Sales V2 cash movement write failed', [
                            'sale_id' => $data->getId(),
                            'error' => $exception->getMessage(),
                        ]);

                        if ($salesWritePolicy instanceof SalesWritePolicy && $salesWritePolicy->strict) {
                            throw new ServiceException(
                                'No se pudo registrar el movimiento de caja en Sales V2. Operación cancelada.'
                            );
                        }
                    }
                }
            }
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

    private function resolveNegativeStockPolicy(): string
    {
        $raw = strtoupper(trim((string) $this->settings->get(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '')));
        if ($raw === self::NEGATIVE_STOCK_STRICT) {
            return self::NEGATIVE_STOCK_BLOCK;
        }
        if ($raw === self::NEGATIVE_STOCK_WARNING_ONLY) {
            return self::NEGATIVE_STOCK_ALLOW_WITH_ROLE;
        }
        if ($raw === self::NEGATIVE_STOCK_ALLOW_NEGATIVE) {
            return self::NEGATIVE_STOCK_ALLOW_ALL;
        }

        if (
            in_array(
                $raw,
                [
                    self::NEGATIVE_STOCK_BLOCK,
                    self::NEGATIVE_STOCK_ALLOW_WITH_ROLE,
                    self::NEGATIVE_STOCK_ALLOW_ALL,
                ],
                true
            )
        ) {
            return $raw;
        }

        return self::NEGATIVE_STOCK_BLOCK;
    }

    private function canSellWithNegativeStock(string $policy, \App\Dto\Auth\AuthUserDto $user): bool
    {
        if ($policy === self::NEGATIVE_STOCK_ALLOW_ALL) {
            return true;
        }

        if ($policy !== self::NEGATIVE_STOCK_ALLOW_WITH_ROLE) {
            return false;
        }

        $roles = $user->roles ?? [];

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }

    private function isStockTrackedProduct(Product $product): bool
    {
        // Convention used in catalog: type=1 means stock-tracked tangible product.
        return $product->getType() === 1;
    }

    private function isPurchasesV2WriteEnabled(): bool
    {
        $mode = strtolower(trim((string) $this->settings->get(Constants::PURCHASES_V2_MODE, 'dual_write')));
        if ($mode === self::PURCHASES_MODE_LEGACY) {
            return false;
        }

        return filter_var(
            $this->settings->get(Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function isPurchasesV2StrictMode(): bool
    {
        return filter_var(
            $this->settings->get(Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
