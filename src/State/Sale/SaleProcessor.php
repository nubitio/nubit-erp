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
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\SaleRecord;
use App\Entity\SaleItem;
use App\Message\SendInvoiceMessage;
use App\Repository\BranchRepository;
use App\Repository\SaleRecordRepository;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\Service\SalePaymentPlanService;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
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
        private SettingsService $settings,
        private InvoiceCreationServiceInterface $invoiceCreationService,
        private LoggerInterface $logger,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private MessageBusInterface $messageBus,
        private SalesWriteGateway $salesWriteGateway,
        private CashSessionResolverInterface $cashSessionResolver,
        private SalePaymentPlanService $salePaymentPlanService,
        private DetractionCalculatorInterface $detractionCalculator,
        private SaleInventoryOrchestrator $inventoryOrchestrator,
        private SaleRecordRepository $saleRecordRepository,
    ) {
    }

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

        // Ensure deterministic movement source keys before inventory ledger writes.
        if ($isNew && $data->getId() === null) {
            $data->setId(Uuid::v7()->toRfc4122());
        }

        $user = $this->security->connectedUser();
        $isOperationalSale = $data->getTipoId() === 2;
        $resolvedCashSessionId = null;
        $requireOpenSession = false;
        $request = $context['request'] ?? null;
        $isOfflineSale = $request instanceof Request && $request->headers->get('X-Offline-Sale') === 'true';

        // Convert writeItems array to SaleItem objects
        if ($isNew && !empty($data->getWriteItems())) {
            $this->logger->info('Converting writeItems to SaleItem objects', [
                'item_count' => count($data->getWriteItems()),
            ]);

            foreach ($this->buildSaleItems($data->getWriteItems()) as $item) {
                $data->addLegacyItem($item);
            }
        }

        if ($isNew && $data->getTipoPago()) {
            $this->logger->info('Processing new sale with payment method', [
                'payment_method' => $data->getTipoPago()->getId(),
            ]);

            $requireOpenSession = filter_var(
                $this->settings->get(Constants::REQUIRE_CASH_REGISTER, 'true'),
                FILTER_VALIDATE_BOOLEAN
            );

            // Check for open cash session in v2 table
            if ($requireOpenSession) {
                $hasOpenSession = (bool) $this->entityManager->getConnection()->fetchOne(
                    "SELECT 1 FROM cash_sessions WHERE opened_by = ? AND status = 'OPEN' LIMIT 1",
                    [$user->userId]
                );
                if (!$hasOpenSession) {
                    $this->logger->warning('Sale processing failed: No open cash session found', [
                        'user_id' => $user->userId,
                    ]);
                    throw new ServiceException('No puedes realizar ventas, no tienes una caja abierta');
                }
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
        $data->setStatus('COMPLETED');
        $this->detractionCalculator->applyFromItems($data);
        if ($isOperationalSale) {
            $this->salePaymentPlanService->resolveForWrite(
                $data,
                strtoupper(trim($data->getCurrency())) ?: 'PEN',
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

        $this->inventoryOrchestrator->process($data, $user, $isOfflineSale);

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
        $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($isOperationalSale) {
            try {
                $cashSessionId = $requireOpenSession
                    ? $this->cashSessionResolver->resolveForSale($data)
                    : $this->cashSessionResolver->tryResolveForSale($data);
                $resolvedCashSessionId = $cashSessionId;

                // Back-propagate cash_register_id from the resolved V2 session onto SaleRecord
                // so that SalesWriteGateway::write() (guard at line 51) sees a non-null value.
                // This is required when the legacy cash_shift lookup returns null (e.g. demo tenant
                // running in v2_authoritative mode where no open cash_shift row exists).
                if ($cashSessionId !== null && $data->getCajaId() === null) {
                    $resolvedRegisterId = $this->cashSessionResolver->resolveRegisterIdForSession($cashSessionId);
                    if ($resolvedRegisterId !== null) {
                        $data->setCashRegisterId($resolvedRegisterId);
                    }
                }

                $this->salesWriteGateway->write($data, $cashSessionId);
            } catch (\Throwable $exception) {
                $this->logger->error('Sales V2 write failed', [
                    'sale_id' => $data->getId(),
                    'branch_id' => $data->getBranch()?->getId() ?? $data->getAlmacen()?->getBranch()?->getId(),
                    'warehouse_id' => $data->getAlmacen()?->getId(),
                    'cash_register_id' => $data->getCajaId(),
                    'resolved_cash_session_id' => $resolvedCashSessionId,
                    'issued_at' => ($data->getFecha() ?? $data->getDateCreated())?->format('Y-m-d H:i:s'),
                    'item_count' => count($data->getItems()),
                    'total' => $data->getTotal(),
                    ...$this->buildThrowableContext($exception),
                ]);

                throw new ServiceException(
                    'No se pudo sincronizar la venta con Sales V2. Operación cancelada.',
                    0,
                    $exception
                );
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
                        $serie  = $data->getSerie() ?? '';
                        $numero = $data->getNumero() ?? '';
                        $saleRef = $serie !== '' && $numero !== '' ? "{$serie}-{$numero}" : null;
                        $this->salesWriteGateway->writeCashSaleMovement(
                            saleId: (string) $data->getId(),
                            cashSessionId: $resolvedCashSessionId,
                            createdBy: $user->userId,
                            amount: $cashCollected,
                            categoryId: null,
                            paymentMethodId: \App\Entity\PaymentMethod::CASH,
                            createdAt: $data->getFecha() !== null ? \DateTimeImmutable::createFromInterface($data->getFecha()) : new DateTimeImmutable(),
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
                            $exception
                        );
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
                    $this->messageBus->dispatch(
                        new SendInvoiceMessage($invoiceResult['invoiceId']),
                        [new DispatchAfterCurrentBusStamp()]
                    );
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

        // Re-fetch from DB to return a fully populated SaleRecord with V2 data
        $this->entityManager->clear();
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

    /**
     * Build SaleItem entities from the raw write-items payload.
     *
     * @param array<int, array<string, mixed>> $writeItems
     *
     * @return SaleItem[]
     */
    private function buildSaleItems(array $writeItems): array
    {
        $items = [];

        foreach ($writeItems as $itemData) {
            $item = new SaleItem();

            // Resolve product from IRI, object with @id / id, or plain UUID
            $productRef = $itemData['product'] ?? null;
            $productId  = $this->extractProductId($productRef);
            if ($productId !== null) {
                $product = $this->entityManager->find(Product::class, $productId);
                $item->setProducto($product);
            }

            $qty       = (float) ($itemData['qty'] ?? $itemData['cantidad'] ?? 1);
            $unitPrice = $itemData['unitPrice'] ?? $itemData['precio_unitario'] ?? '0.00';
            $discount  = $itemData['discount'] ?? $itemData['descuento'] ?? '0.00';

            // Calculate item total: (qty × unitPrice) − discount
            $itemTotal = $itemData['total'] ?? $itemData['precio_total'] ?? null;
            if ($itemTotal === null || $itemTotal === '' || $itemTotal === '0.00') {
                $lineTotal = Money::money(BigDecimal::of($unitPrice)->multipliedBy($qty));
                $itemTotal = Money::toString($lineTotal->minus(Money::money($discount)));
            }

            $item->setCantidad($qty);
            $item->setPrecioUnitario($unitPrice);
            $item->setDescuento($discount);
            $item->setPrecioTotal($itemTotal);

            $this->logger->debug('Converted sale item', [
                'product_id' => $item->getProducto()?->getId(),
                'qty'        => $qty,
                'unit_price' => $unitPrice,
                'discount'   => $discount,
                'total'      => $itemTotal,
            ]);

            $items[] = $item;
        }

        return $items;
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

        $defaultCustomer = $this->entityManager->find(Customer::class, 1);
        if (!$defaultCustomer instanceof Customer) {
            throw new ServiceException(
                'Debe seleccionar un cliente para la venta y no existe el cliente general (ID 1).'
            );
        }

        $sale->setCliente($defaultCustomer);
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
