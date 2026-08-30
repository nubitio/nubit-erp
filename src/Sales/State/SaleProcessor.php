<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Efact\Domain\Money;
use App\Identity\Auth\BranchContextService;
use App\Identity\Security\Security;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\SalePaymentPlanService;
use App\Settings\Service\TenantSettingsService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\DomainErrorCode;
use Nubit\Platform\Exception\DomainProblemException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Exception\ValidationException;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class SaleProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     * @param ProcessorInterface<mixed, mixed> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private SaleUtils $saleUtils,
        private TenantSettingsService $tenantSettings,
        private InvoiceCreatorInterface $saleInvoicing,
        private LoggerInterface $logger,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private SalesWriteGateway $salesWriteGateway,
        private CashSessionResolverInterface $cashSessionResolver,
        private SalePaymentPlanService $salePaymentPlanService,
        private DetractionCalculatorInterface $detractionCalculator,
        private SaleInventoryOrchestrator $inventoryOrchestrator,
        private SaleRecordRepository $saleRecordRepository,
        private CustomerRepository $customerRepository,
        private ActiveReferenceGuard $activeReferenceGuard,
        private ?MessageBusInterface $messageBus = null,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SaleRecord $data
     *
     * @throws ServiceException
     * @throws Exception
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
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

        // Ensure deterministic movement source keys before inventory inventory movements.
        if ($isNew && $data->getId() === null) {
            $data->setId(Uuid::v7()->toRfc4122());
        }

        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new ServiceException('Usuario no autenticado.');
        }

        $isOperationalSale = $data->getTipoId() === 2;
        $resolvedCashSessionId = null;
        $requireOpenSession = false;
        $request = $context['request'] ?? null;
        // Offline mode marks an idempotent outbox replay; it never weakens stock or commercial validation.
        // A well-formed key is required so retries cannot duplicate the physical transaction.
        $offlineSaleHeader = $request instanceof Request && $request->headers->get('X-Offline-Sale') === 'true';
        $idempotencyKey = $request instanceof Request ? $request->headers->get('Idempotency-Key') ?? '' : '';
        $isUuidIdempotencyKey =
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $idempotencyKey) === 1;
        $isOfflineSale = $offlineSaleHeader && $isUuidIdempotencyKey;
        if ($offlineSaleHeader && !$isOfflineSale) {
            $this->logger->warning('X-Offline-Sale header rejected: missing or malformed Idempotency-Key', [
                'user_id' => $user->userId,
                'idempotency_key' => $idempotencyKey ?: '(empty)',
                'remote_addr' => $request->getClientIp(),
            ]);
        }
        if ($isOfflineSale && !$this->security->hasPermission('pos.offline_sale')) {
            throw new AccessDeniedException('Permission "pos.offline_sale" is required to synchronize offline sales.');
        }

        // Idempotent replay: POS double-clicks and the offline outbox both resend
        // the same Idempotency-Key. Without this check a retry whose first attempt
        // reached the server would create a second SaleRecord, double-deduct stock,
        // and double-invoice the customer. Checked before any side effect runs.
        if ($isNew && $isUuidIdempotencyKey) {
            $existingSale = $this->saleRecordRepository->findByOfflineRetryKey($idempotencyKey);
            if ($existingSale instanceof SaleRecord) {
                $this->logger->info('Sale replay detected via Idempotency-Key — returning existing sale', [
                    'sale_id' => $existingSale->getId(),
                    'idempotency_key' => $idempotencyKey,
                    'offline' => $isOfflineSale,
                ]);
                $this->saleRecordRepository->enrichWithTaxDocuments([$existingSale]);

                return $existingSale;
            }
        }

        // Disabled branches stay readable for reports but must not accept new sales,
        // even from sessions whose token was issued before the branch was disabled.
        if ($isNew) {
            $contextBranchId = $this->branchContextService->getCurrentBranchId();
            $contextBranch = $contextBranchId !== null ? $this->branchRepository->find($contextBranchId) : null;
            if ($contextBranch !== null && !$contextBranch->getIsActive()) {
                $this->logger->warning('Sale rejected: branch is disabled', [
                    'user_id' => $user->userId,
                    'branch_id' => $contextBranchId,
                ]);
                throw ValidationException::withErrors([
                    'branch' => ['La sucursal está deshabilitada; no se pueden registrar nuevas ventas.'],
                ]);
            }
        }

        // Disabled customers/warehouses stay readable for reports but must not be
        // wired into new sales; editing an existing sale is unaffected.
        if ($isNew) {
            $this->activeReferenceGuard->assertActiveOnCreate($data->getCustomer(), 'customer', 'El cliente');
            $this->activeReferenceGuard->assertActiveOnCreate($data->getWarehouse(), 'warehouse', 'El almacén');
        }

        // Convert writeItems array to SaleItemRecord objects
        if ($isNew && !empty($data->getWriteItems())) {
            $this->logger->info('Converting writeItems to SaleItemRecord objects', [
                'item_count' => count($data->getWriteItems()),
            ]);

            foreach ($this->buildSaleItems($data->getWriteItems()) as $item) {
                $data->addSaleItemRecord($item);
            }
        }

        if ($isNew && $data->getTipoPago()) {
            $this->logger->info('Processing new sale with payment method', [
                'payment_method' => $data->getTipoPago()->getId(),
            ]);

            $requireOpenSession = $this->tenantSettings->isRequireCashRegister();

            // Check for an open V2 cash session. Only cash_sessions (V2) is checked here because
            // CashSessionResolver also only resolves against cash_sessions — accepting a legacy
            // cash_shift row here would let the sale proceed past this guard and then fail later
            // with a cryptic "Sales V2 write failed" error when the resolver can't find a session.
            if ($requireOpenSession) {
                $hasOpenSession =
                    (int) $this->entityManager
                        ->createQuery(<<<'DQL'
                            SELECT COUNT(cs.id)
                            FROM App\CashRegister\Entity\CashSession cs
                            WHERE cs.openedBy = :userId
                              AND cs.status = :status
                            DQL)
                        ->setParameter('userId', $user->userId)
                        ->setParameter('status', 'OPEN')
                        ->getSingleScalarResult() > 0;

                if (!$hasOpenSession) {
                    $this->logger->warning('Sale processing failed: No open cash session found', [
                        'user_id' => $user->userId,
                    ]);
                    throw new DomainProblemException(
                        errorCode: DomainErrorCode::SaleCashSessionRequired,
                        detail: 'No puedes realizar ventas, no tienes una caja abierta',
                        title: 'Cash register session required',
                        type: '/errors/sale-cash-session-required',
                        action: 'OPEN_CASH_REGISTER',
                        numericCode: 1000,
                    );
                }
            }

            $data = $this->saleUtils->fillSaleValues($data);
            $this->ensureSaleCustomer($data);
            $this->logger->debug('Sale values filled');
        }

        if ($isOperationalSale) {
            $this->validateCommercialControls($data);
        }

        $this->logger->info('Calculating sale totals');
        $subtotal = BigDecimal::zero();
        $itemCount = 0;

        /** @var SaleItemRecord $item */
        foreach ($data->getSaleItemRecords() as $item) {
            $itemCount++;
            $item->setSaleRecord($data);
            $subtotal = $subtotal->plus(Money::money($item->getNetAmount()));

            $this->logger->debug('Processed sale item', [
                'item_number' => $itemCount,
                'product_id' => $item->getProduct()?->getId(),
                'quantity' => $item->getQty(),
                'price' => $item->getUnitPrice(),
                'total' => $item->getNetAmount(),
            ]);
        }

        $data->setSubtotal(Money::toString($subtotal));
        $discountRate = Money::rate($data->getDescuento());
        $discountAmount = Money::money($subtotal->multipliedBy($discountRate));
        $totalBeforeCashRounding = Money::money($subtotal->minus($discountAmount));
        $cashRoundingAdjustment = $this->resolveCashRoundingAdjustment($data, $totalBeforeCashRounding, $request);
        $total = Money::money($totalBeforeCashRounding->minus($cashRoundingAdjustment));
        $data->setTotal(Money::toString($total));
        $data->setCashRoundingAdjustment(Money::toString($cashRoundingAdjustment));
        $data->setStatus('COMPLETED');
        $this->detractionCalculator->applyFromItems($data);
        if ($isOperationalSale) {
            $this->salePaymentPlanService->resolveForWrite(
                $data,
                strtoupper(trim($data->getCurrency())) ?: 'PEN',
                $data->getTotal() ?? '0.00',
                $data->isCreditSale(),
            );
        }

        $this->logger->info('Sale totals calculated', [
            'item_count' => $itemCount,
            'subtotal' => Money::toString($subtotal),
            'discount_percentage' => $data->getDescuento(),
            'cash_rounding_adjustment' => Money::toString($cashRoundingAdjustment),
            'total' => Money::toString($total),
        ]);

        if ($isNew) {
            // `has_invoice` reflects the actual emitted document, not a client intent flag.
            $data->setHasInvoice(false);
        }

        $this->inventoryOrchestrator->process($data, $user, false);

        if ($isNew) {
            $this->logger->info('Creating new sale record with timestamp');
            $data->setDateCreated(new DateTimeImmutable());
            $data->setCreatedBy($user->userId);
            if ($isUuidIdempotencyKey) {
                $data->setOfflineRetryKey($idempotencyKey);
            }

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
            'item_count' => count($data->getSaleItemRecords()),
        ]);

        if ($isOperationalSale) {
            // Resolve cash session and back-propagate IDs onto $data BEFORE the ORM flush so
            // that seller_user_id, cash_register_id, and cash_session_id are non-null on INSERT.
            try {
                $cashSessionId = $requireOpenSession
                    ? $this->cashSessionResolver->resolveForSale($data)
                    : $this->cashSessionResolver->tryResolveForSale($data);
                $resolvedCashSessionId = $cashSessionId;

                // Back-propagate cash_register_id from the resolved V2 session onto SaleRecord.
                if ($cashSessionId !== null && $data->getCajaId() === null) {
                    $resolvedRegisterId = $this->cashSessionResolver->resolveRegisterIdForSession($cashSessionId);
                    if ($resolvedRegisterId !== null) {
                        $data->setCashRegisterId($resolvedRegisterId);
                    }
                }

                // write() populates all V2 fields on $data (seller_user_id, sync fields, etc.)
                // and calls entityManager->flush() — so persistProcessor is not needed for
                // operational sales.
                $this->salesWriteGateway->write($data, $cashSessionId);
            } catch (\Throwable $exception) {
                $this->logger->error('Sales V2 write failed', [
                    'sale_id' => $data->getId(),
                    'branch_id' => $data->getBranch()?->getId() ?? $data->getAlmacen()?->getBranch()?->getId(),
                    'warehouse_id' => $data->getAlmacen()?->getId(),
                    'cash_register_id' => $data->getCajaId(),
                    'resolved_cash_session_id' => $resolvedCashSessionId,
                    'issued_at' => ($data->getFecha() ?? $data->getDateCreated())?->format('Y-m-d H:i:s'),
                    'item_count' => count($data->getSaleItemRecords()),
                    'total' => $data->getTotal(),
                    ...$this->buildThrowableContext($exception),
                ]);

                throw new ServiceException(
                    'No se pudo sincronizar la venta con Sales V2. Operación cancelada.',
                    0,
                    $exception,
                );
            }
        } else {
            // Non-operational sales: use the standard API Platform persist processor.
            $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($isNew && $isOperationalSale) {
            $cashCollected = $this->salePaymentPlanService->resolveCashCollectedAmount(
                $data,
                $data->getTotal() ?? '0.00',
                $data->isCreditSale(),
            );
            if (Money::money($cashCollected)->isGreaterThan(BigDecimal::zero())) {
                if ($resolvedCashSessionId !== null) {
                    try {
                        $serie = $data->getSerie() ?? '';
                        $numero = $data->getNumero() ?? '';
                        $saleRef = $serie !== '' && $numero !== '' ? "{$serie}-{$numero}" : null;
                        $this->salesWriteGateway->writeCashSaleMovement(
                            saleId: (string) $data->getId(),
                            cashSessionId: $resolvedCashSessionId,
                            createdBy: $user->userId,
                            amount: $cashCollected,
                            categoryId: null,
                            paymentMethodId: \App\Catalog\Entity\PaymentMethod::CASH,
                            createdAt: $data->getFecha() !== null
                                ? \DateTimeImmutable::createFromInterface($data->getFecha())
                                : new DateTimeImmutable(),
                            description: $saleRef !== null ? "Venta {$saleRef}" : 'Venta en efectivo',
                        );
                    } catch (\Throwable $exception) {
                        $this->logger->error('Sales V2 cash movement write failed', [
                            'sale_id' => $data->getId(),
                            'cash_session_id' => $resolvedCashSessionId,
                            'amount' => $cashCollected,
                            ...$this->buildThrowableContext($exception),
                        ]);

                        throw new ServiceException(
                            'No se pudo registrar el movimiento de caja en Sales V2. Operación cancelada.',
                            0,
                            $exception,
                        );
                    }
                }
            }
        }

        $autoInvoiceEnabled = $this->tenantSettings->isAutoInvoice();
        $emitInvoice = $data->getEmitInvoice();
        $shouldEmitInvoice = $emitInvoice !== null ? $emitInvoice : $autoInvoiceEnabled;
        if ($isNew && $data->getTipoId() === 2 && $shouldEmitInvoice) {
            try {
                $invoiceResult = $this->saleInvoicing->createAndDispatchInvoiceForSaleId((string) $data->getId());
                if (!$invoiceResult['success']) {
                    $this->logger->warning('Auto invoice skipped', [
                        'sale_id' => $data->getId(),
                        'message' => $invoiceResult['message'],
                    ]);
                }
            } catch (\Throwable $throwable) {
                $this->logger->error('Auto invoice failed', [
                    'sale_id' => $data->getId(),
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        if ($isNew && $this->messageBus !== null && $data->getTipoId() === 2) {
            try {
                $this->dispatchSaleToLedger($data);
            } catch (\Throwable $exception) {
                $this->logger->error('Accounting post for sale failed', [
                    'sale_id' => $data->getId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger->info('Sale processing completed successfully', [
            'sale_id' => $data->getId(),
        ]);

        // Re-fetch from DB to return a fully populated SaleRecord with V2 data.
        // Detach only $data so UnitOfWork entities loaded in this request are not lost.
        $this->entityManager->detach($data);
        $record = $this->saleRecordRepository->findByAnyId((string) $data->getId());
        if ($record !== null) {
            $this->saleRecordRepository->enrichWithTaxDocuments([$record]);

            return $record;
        }

        // Synthetic fallback: sale was written but not yet visible (e.g. race on v2_authoritative mode)
        $this->logger->warning('SaleRecord not found after processing, returning synthetic record.', [
            'sale_id' => $data->getId(),
        ]);

        return $data;
    }

    private function dispatchSaleToLedger(SaleRecord $sale): void
    {
        $cogs = BigDecimal::zero();
        foreach ($sale->getSaleItemRecords() as $item) {
            $cogs = $cogs->plus(Money::mul(Money::of($item->getQty()), Money::of($item->getUnitCostSnapshot() ?? '0')));
        }

        $issued = $sale->getIssuedAt() ?? new DateTimeImmutable();
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';
        $label = $serie !== '' && $numero !== '' ? "{$serie}-{$numero}" : (string) $sale->getId();

        $this->messageBus?->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_SALE,
            sourceId: (string) $sale->getId(),
            kind: $sale->isCreditSale() ? EconomicEvent::SALE_CREDIT : EconomicEvent::SALE_CASH,
            occurredOn: $issued->format('Y-m-d'),
            description: 'Venta ' . $label,
            total: $sale->getTotal() ?? '0.00',
            tax: $sale->getTaxTotal(),
            cogs: Money::toString($cogs),
            currency: $sale->getCurrency() ?? 'PEN',
        ));
    }

    /**
     * Build SaleItemRecord entities from the raw write-items payload.
     *
     * @param array<int, array<string, mixed>> $writeItems
     *
     * @return SaleItemRecord[]
     */
    private function buildSaleItems(array $writeItems): array
    {
        $items = [];
        $lineNo = 1;

        foreach ($writeItems as $itemData) {
            $item = new SaleItemRecord();

            // Resolve product from IRI, object with @id / id, or plain UUID
            $productRef = $itemData['product'] ?? null;
            $productId = $this->extractProductId($productRef);
            $product = null;
            if ($productId !== null) {
                $product = $this->entityManager->find(Product::class, $productId);
                $item->setProduct($product);
            }

            $rawQty = $itemData['qty'] ?? $itemData['cantidad'] ?? 1;
            $unitPrice = $itemData['unitPrice'] ?? $itemData['precio_unitario'] ?? '0.00';
            $discount = $itemData['discount'] ?? $itemData['descuento'] ?? null;

            if (!is_numeric($rawQty) || (float) $rawQty <= 0) {
                throw ValidationException::withErrors(['qty' => ['La cantidad debe ser un número mayor que cero.']]);
            }
            if (!is_numeric($unitPrice) || (float) $unitPrice < 0) {
                throw ValidationException::withErrors(['unitPrice' => [
                    'El precio unitario debe ser un número mayor o igual que cero.',
                ]]);
            }

            $qty = (float) $rawQty;

            // Resolve discountPercent: prefer explicit percent over legacy flat amount.
            $discountPercent = $itemData['discountPercent'] ?? $itemData['descuento_porcentaje'] ?? null;

            if ($discountPercent !== null) {
                if (!is_numeric($discountPercent)) {
                    throw ValidationException::withErrors([
                        'discountPercent' => ['El porcentaje de descuento debe ser numérico.'],
                    ]);
                }
                $percentValue = (float) $discountPercent;

                if ($percentValue < 0 || $percentValue > 100) {
                    throw ValidationException::withErrors([
                        'discountPercent' => [
                            sprintf(
                                'El porcentaje de descuento debe estar entre 0 y 100. Valor recibido: %s.',
                                $discountPercent,
                            ),
                        ],
                    ]);
                }

                // discountAmount = qty × unitPrice × (discountPercent / 100), HALF_UP to 2 dp
                $discountAmount = Money::money(BigDecimal::of((string) $unitPrice)->multipliedBy(
                    $qty,
                )->multipliedBy(Money::rate($discountPercent)));
                $discount = Money::toString($discountAmount);
            }

            $discount = (string) ($discount ?? '0.00');

            // gross = qty × unitPrice
            $grossAmount = Money::money(BigDecimal::of((string) $unitPrice)->multipliedBy($qty));
            if (
                !is_numeric($discount)
                || (float) $discount < 0
                || Money::money($discount)->isGreaterThan($grossAmount)
            ) {
                throw ValidationException::withErrors([
                    'discount' => ['El descuento de línea debe estar entre cero y el importe bruto de la línea.'],
                ]);
            }
            // net = gross − discount  (always computed server-side; any client-supplied total is ignored
            // to prevent price manipulation via the write-items payload)
            $itemTotal = Money::toString(Money::money($grossAmount)->minus(Money::money($discount)));

            $item->setLineNo($lineNo++);
            $item->setQty(number_format($qty, 3, '.', ''));
            $item->setUnitPrice(number_format((float) $unitPrice, 4, '.', ''));
            $item->setGrossAmount(Money::toString($grossAmount));
            $item->setDiscountAmount($discount);
            $item->setNetAmount($itemTotal);
            $item->setTaxAmount('0.00');

            if ($discountPercent !== null) {
                $item->setDiscountPercent((string) $discountPercent);
            }

            $description = trim(
                (string) ($itemData['description'] ?? $itemData['nameSnapshot'] ?? $itemData['descripcion'] ?? ''),
            );

            if ($description !== '') {
                $item->setNameSnapshot(mb_substr($description, 0, 255));
            }

            // Populate snapshots from product when available
            if ($product !== null) {
                if ($item->getNameSnapshot() === '') {
                    $item->setNameSnapshot($product->getName());
                }
                $item->setSkuSnapshot($product->getCode());
                $item->setUomSnapshot($product->getUnitOfMeasure()->getCode());
                $item->setUnitCostSnapshot($product->getPurchasePrice());
            }

            $this->logger->debug('Converted sale item', [
                'product_id' => $product?->getId(),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount' => $discount,
                'total' => $itemTotal,
            ]);

            $items[] = $item;
        }

        return $items;
    }

    private function validateCommercialControls(SaleRecord $sale): void
    {
        $globalDiscount = $sale->getDescuento() ?? '0';
        if (!is_numeric($globalDiscount) || (float) $globalDiscount < 0 || (float) $globalDiscount > 100) {
            throw ValidationException::withErrors([
                'discountPercent' => ['El descuento global debe estar entre 0 y 100.'],
            ]);
        }

        $hasDiscount = (float) $globalDiscount > 0;
        $hasPriceOverride = false;
        foreach ($sale->getSaleItemRecords() as $item) {
            $hasDiscount = $hasDiscount || (float) $item->getDiscountAmount() > 0;
            $product = $item->getProduct();
            if (!$product instanceof Product) {
                throw ValidationException::withErrors([
                    'product' => ['Cada línea de venta debe tener un producto válido.'],
                ]);
            }
            if (!$this->isAuthorizedPrice($sale->getCustomer(), $product, $item->getUnitPrice())) {
                $hasPriceOverride = true;
            }
        }

        if ($hasDiscount && !$this->security->hasPermission('pos.discount')) {
            throw new AccessDeniedException('Permission "pos.discount" is required to apply discounts.');
        }
        if ($hasPriceOverride && !$this->security->hasPermission('pos.price_override')) {
            throw new AccessDeniedException('Permission "pos.price_override" is required to modify prices.');
        }
    }

    private function isAuthorizedPrice(?Customer $customer, Product $product, string $unitPrice): bool
    {
        $expectedPrice = $product->getSalePrice();
        $priceList = $customer?->getPriceList();
        if ($priceList !== null && $priceList->getId() !== null) {
            $explicitPrice = $this->entityManager->getConnection()->fetchOne('SELECT price FROM price_list_detail WHERE price_list_id = :priceList AND product_id = :product', [
                'priceList' => $priceList->getId(),
                'product' => $product->getId(),
            ]);
            if ($explicitPrice !== false) {
                $expectedPrice = (string) $explicitPrice;
            } else {
                $multiplier = $priceList->getMultiplier();
                if ($multiplier !== null) {
                    $expectedPrice = (string) BigDecimal::of($expectedPrice)->multipliedBy($multiplier);
                }
            }
        }

        return BigDecimal::of($unitPrice)->compareTo(BigDecimal::of($expectedPrice)) === 0;
    }

    /**
     * Resolve a product identifier from various input shapes:
     *  - IRI string:        "/api/products/{uuid}"
     *  - Object with @id:   ["@id" => "/api/products/{uuid}", ...]
     *  - Object with id:    ["id" => "{uuid}", ...]
     *  - Plain UUID string: "{uuid}"
     *
     * Returns null when $productRef is null or cannot be resolved.
     */
    private function extractProductId(mixed $productRef): ?string
    {
        if ($productRef === null) {
            return null;
        }

        // Unwrap array shapes: { "@id": "/api/products/…" } or { "id": "…" }
        if (is_array($productRef)) {
            $productRef = $productRef['@id'] ?? $productRef['id'] ?? null;
            if ($productRef === null) {
                return null;
            }
        }

        if (!is_string($productRef)) {
            return null;
        }

        // Strip IRI prefix
        if (str_starts_with($productRef, '/api/products/')) {
            return substr($productRef, strlen('/api/products/'));
        }

        // Plain UUID / numeric ID — return as-is
        return $productRef;
    }

    private function ensureSaleCustomer(SaleRecord $sale): void
    {
        if ($sale->getTipoId() !== 2 || $sale->getCliente() instanceof Customer) {
            return;
        }

        $defaultCustomer = $this->customerRepository->findDefaultAnonymous();
        if (!$defaultCustomer instanceof Customer) {
            throw new ServiceException(
                'Debe seleccionar un cliente para la venta y no existe el cliente general (ruc=00000000).',
            );
        }

        $sale->setCliente($defaultCustomer);
    }

    /**
     * Recalculate the optional cash adjustment server-side. This field must
     * never become a client-controlled discount.
     */
    private function resolveCashRoundingAdjustment(SaleRecord $sale, BigDecimal $total, ?Request $request): BigDecimal
    {
        $payload = $request instanceof Request ? json_decode($request->getContent(), true) : null;
        $requested = Money::money(
            is_array($payload) && array_key_exists('cashRoundingAdjustment', $payload)
                ? $payload['cashRoundingAdjustment']
                : $sale->getCashRoundingAdjustment(),
        );
        if ($requested->isZero()) {
            return BigDecimal::zero();
        }

        if (!$this->tenantSettings->isCashRoundingEnabled()) {
            throw new ServiceException('El redondeo de efectivo no está habilitado para este negocio.');
        }

        if ($sale->isCreditSale() || strtoupper(trim($sale->getCurrency())) !== 'PEN') {
            throw new ServiceException('El redondeo aplica únicamente a ventas al contado en soles.');
        }

        $payments = $sale->getPayments();
        if (count($payments) !== 1 || (int) ($payments[0]['payment_method_id'] ?? 0) !== PaymentMethod::CASH) {
            throw new ServiceException('El redondeo aplica únicamente a pagos 100% en efectivo.');
        }

        $roundedTotal = $total
            ->dividedBy('0.10', 0, RoundingMode::DOWN)
            ->multipliedBy('0.10')
            ->toScale(2, RoundingMode::HALF_UP);
        $expected = Money::money($total->minus($roundedTotal));

        if (!$requested->isEqualTo($expected)) {
            throw new ServiceException('El ajuste de redondeo de efectivo no coincide con el total de la venta.');
        }

        return $expected;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildThrowableContext(\Throwable $exception): array
    {
        $chain = [];
        $cursor = $exception;
        $depth = 0;
        while ($cursor !== null && $depth < 5) {
            $chain[] = [
                'class' => get_class($cursor),
                'code' => $cursor->getCode(),
                'message' => $cursor->getMessage(),
                'file' => $cursor->getFile(),
                'line' => $cursor->getLine(),
            ];
            $cursor = $cursor->getPrevious();
            ++$depth;
        }

        $rootCause = $chain[count($chain) - 1] ?? null;
        $traceLines = array_slice(explode("\n", $exception->getTraceAsString()), 0, 8);

        return [
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_chain' => $chain,
            'exception_trace_top' => $traceLines,
            'root_cause_class' => is_array($rootCause) ? $rootCause['class'] : null,
            'root_cause_message' => is_array($rootCause) ? $rootCause['message'] : null,
            'root_cause_code' => is_array($rootCause) ? $rootCause['code'] : null,
        ];
    }
}
