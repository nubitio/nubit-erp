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
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Message\SendInvoiceMessage;
use App\Repository\BranchRepository;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\Service\SalePaymentPlanService;
use App\State\Purchase\GoodsReceiptWriteGateway;
use App\State\Purchase\PurchasesWritePolicyResolver;
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
        private SalesWritePolicyResolver $salesWritePolicyResolver,
        private SalesWriteGateway $salesWriteGateway,
        private GoodsReceiptWriteGateway $goodsReceiptWriteGateway,
        private PurchasesWritePolicyResolver $purchasesWritePolicyResolver,
        private CashSessionResolver $cashSessionResolver,
        private SalePaymentPlanService $salePaymentPlanService,
        private DetractionCalculator $detractionCalculator,
        private SaleInventoryOrchestrator $inventoryOrchestrator,
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
        $isOperationalSale = $data->getTipoId() === 2;
        $salesWritePolicy = $isOperationalSale ? $this->salesWritePolicyResolver->resolve() : null;
        $resolvedCashSessionId = null;
        $requireCashShift = false;
        $request = $context['request'] ?? null;
        $isOfflineSale = $request instanceof Request && $request->headers->get('X-Offline-Sale') === 'true';

        if ($isNew && $data->getTipoPago()) {
            $this->logger->info('Processing new sale with payment method', [
                'payment_method' => $data->getTipoPago()->getId(),
            ]);

            $requireCashShift = filter_var(
                $this->settings->get(Constants::REQUIRE_CASH_REGISTER, 'true'),
                FILTER_VALIDATE_BOOLEAN
            );

            // Check for open cash session in v2 table
            if ($requireCashShift) {
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
        $data->setEstado(1);
        $this->detractionCalculator->applyFromItems($data);
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

        $purchasesPolicy = $this->purchasesWritePolicyResolver->resolve();
        $skipLegacyPersist = ($data->getTipoId() === 1 && $purchasesPolicy->skipLegacyPersist)
            || ($isOperationalSale
                && $salesWritePolicy instanceof SalesWritePolicy
                && $salesWritePolicy->skipLegacyPersist);

        if ($skipLegacyPersist) {
            $this->logger->info('Skipping legacy movimiento persist (v2_authoritative)', [
                'movimiento_id' => $data->getId(),
                'tipo_id' => $data->getTipoId(),
            ]);
            $result = $data;
        } else {
            $this->logger->info('Persisting sale data', [
                'sale_id' => $data->getId(),
                'customer_id' => $data->getCliente()?->getId(),
                'total' => $data->getTotal(),
                'item_count' => count($data->getItems()),
            ]);
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($data->getTipoId() === 1 && $purchasesPolicy->writeNewSchema) {
            try {
                $this->goodsReceiptWriteGateway->write($data);
            } catch (\Throwable $exception) {
                $this->logger->error('Goods receipts V2 write failed', [
                    'movimiento_id' => $data->getId(),
                    'mode' => $purchasesPolicy->mode,
                    'strict' => $purchasesPolicy->strict,
                    'branch_id' => $data->getBranch()?->getId() ?? $data->getAlmacen()?->getBranch()?->getId(),
                    'warehouse_id' => $data->getAlmacen()?->getId(),
                    ...$this->buildThrowableContext($exception),
                ]);

                if ($purchasesPolicy->strict) {
                    throw new ServiceException(
                        'No se pudo sincronizar la compra con Compras V2. Operación cancelada.',
                        0,
                        $exception
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
                    $cashSessionId = $requireCashShift
                        ? $this->cashSessionResolver->resolveForSale($data)
                        : $this->cashSessionResolver->tryResolveForSale($data);
                    $resolvedCashSessionId = $cashSessionId;
                    $this->salesWriteGateway->write($data, $cashSessionId);
                } catch (\Throwable $exception) {
                    $this->logger->error('Sales V2 dual-write failed', [
                        'sale_id' => $data->getId(),
                        'mode' => $salesWritePolicy->mode,
                        'strict' => $salesWritePolicy->strict,
                        'branch_id' => $data->getBranch()?->getId() ?? $data->getAlmacen()?->getBranch()?->getId(),
                        'warehouse_id' => $data->getAlmacen()?->getId(),
                        'cash_register_id' => $data->getCajaId(),
                        'resolved_cash_session_id' => $resolvedCashSessionId,
                        'issued_at' => ($data->getFecha() ?? $data->getDateCreated())?->format('Y-m-d H:i:s'),
                        'item_count' => count($data->getItems()),
                        'total' => $data->getTotal(),
                        ...$this->buildThrowableContext($exception),
                    ]);

                    if ($salesWritePolicy->strict) {
                        throw new ServiceException(
                            'No se pudo sincronizar la venta con Sales V2. Operación cancelada.',
                            0,
                            $exception
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
                            createdAt: $data->getFecha() ?? new DateTimeImmutable(),
                            description: $saleRef !== null ? "Venta {$saleRef}" : 'Venta en efectivo',
                        );
                    } catch (\Throwable $exception) {
                        $this->logger->error('Sales V2 cash movement write failed', [
                            'sale_id' => $data->getId(),
                            'cash_session_id' => $resolvedCashSessionId,
                            'amount' => $cashCollected,
                            ...$this->buildThrowableContext($exception),
                        ]);

                        if ($salesWritePolicy instanceof SalesWritePolicy && $salesWritePolicy->strict) {
                            throw new ServiceException(
                                'No se pudo registrar el movimiento de caja en Sales V2. Operación cancelada.',
                                0,
                                $exception
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

        return $result;
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
