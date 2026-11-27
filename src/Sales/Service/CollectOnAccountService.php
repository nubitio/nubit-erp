<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Dto\CollectOnAccountApplication;
use App\Sales\Dto\CollectOnAccountResult;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\State\SalesWriteGateway;
use App\Treasury\Domain\MovementSource;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * "Cobro a cuenta" (#381, epic #367): one payment received from a customer,
 * applied across several of their open sales.
 *
 * The money is booked once — a single cash-drawer movement and a single
 * EconomicEvent for the whole amount — while the debt side is N `sale_payments`
 * rows (one per sale settled), each mirrored by a `customer_payment_applications`
 * row and a `payment` entry on the customer ledger.
 * {@see SalePaymentService::appendPayment()} does the per-sale row; this service
 * owns the once-only booking and {@see CollectOnAccountPlanner} the split.
 *
 * The amount must not exceed the total applicable debt: leaving a remainder as a
 * customer advance is #382 (its accounting is #383).
 *
 * @phpstan-import-type PlanRow from CollectOnAccountPlanner
 */
readonly class CollectOnAccountService
{
    private const string EXCESS_HINT = 'Dejar el excedente como anticipo del cliente aún no está disponible.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRecordRepository $sales,
        private CollectOnAccountPlanner $planner,
        private SalePaymentService $salePaymentService,
        private CustomerAccountLedger $ledger,
        private SalesWriteGateway $salesWriteGateway,
        private MessageBusInterface $messageBus,
        private OperationLog $operationLog,
        private ?TreasuryReceipts $treasuryReceipts = null,
        private ?CustomerAccountEntryRepository $entries = null,
    ) {}

    /**
     * @param list<array{saleId: string, amount: string|float}> $applications
     *        empty = FIFO over every open sale, oldest first
     * @param string|null $collectionId stable id derived from the caller's Idempotency-Key; when a collection
     *        with it already exists its result is returned and nothing is booked again
     */
    public function collect(
        int $customerId,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        array $applications,
        User $createdBy,
        ?string $moneyAccountId = null,
        ?string $collectionId = null,
    ): CollectOnAccountResult {
        if ($collectionId !== null) {
            $replayed = $this->replay($collectionId, $customerId);
            if ($replayed instanceof CollectOnAccountResult) {
                return $replayed;
            }
        }

        CollectionPlanGuard::assertCollectableMethod($paymentMethod);

        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto del cobro debe ser mayor a 0.');
        }

        $openSales = $this->sales->findOpenReceivablesForCustomer($customerId);
        if ($openSales === []) {
            throw new UnprocessableEntityHttpException('El cliente no tiene comprobantes pendientes de cobro.');
        }

        $plan = $this->planner->plan($openSales, $amountMoney, $applications, self::EXCESS_HINT);

        $currency = CollectionPlanGuard::currencyOf($plan);
        $isCash = $paymentMethod->getId() === PaymentMethod::CASH;
        $cashSessionId = $isCash
            ? $this->resolveCashSession($createdBy, $plan[0]['sale']->getBranch()?->getId())
            : null;

        $callerSuppliedKey = $collectionId !== null;
        $collectionId ??= Uuid::v4()->toRfc4122();
        $description = sprintf('Cobro a cuenta cliente #%d', $customerId);

        $lines = $this->bookOnce(fn(): array => $this->entityManager->wrapInTransaction(function () use (
            $callerSuppliedKey,
            $applications,
            $paymentMethod,
            $paidAt,
            $reference,
            $customerId,
            $currency,
            $collectionId,
            $amountMoney,
            $createdBy,
            $moneyAccountId,
            $cashSessionId,
            $description,
        ): array {
            if ($cashSessionId !== null) {
                $this->salesWriteGateway->requireLockedOpenCashSessionForUser(
                    $cashSessionId,
                    (int) $createdBy->getId(),
                );
                $this->salesWriteGateway->writeOnAccountCollectionMovement(
                    collectionId: $collectionId,
                    cashSessionId: $cashSessionId,
                    createdBy: (int) $createdBy->getId(),
                    amount: Money::toString($amountMoney),
                    paymentMethodId: PaymentMethod::CASH,
                    createdAt: $paidAt,
                    description: $description,
                );
            }

            // A concurrent request with the same key may have booked it while this one waited for the lock.
            if ($callerSuppliedKey && ($this->entries?->findCollectionApplications($collectionId) ?? []) !== []) {
                throw new ConflictHttpException('Este cobro ya fue registrado. Reintente para ver su resultado.');
            }

            // Re-read the debt under a row lock: the plan above was made on a snapshot another
            // collection may already have spent (G-01). The lock is held until this transaction ends.
            $lockedPlan = $this->planUnderLock($customerId, $amountMoney, $applications);

            $lines = $this->applyPlan(
                $lockedPlan,
                $paymentMethod,
                $paidAt,
                $reference,
                $customerId,
                $currency,
                $collectionId,
                $amountMoney,
                $createdBy,
                $moneyAccountId,
            );
            $this->bookAccountingEvent(
                $collectionId,
                $amountMoney,
                $currency,
                $cashSessionId !== null,
                $paidAt,
                $description,
            );

            return $lines;
        }));

        $this->operationLog->record('sales', 'receivable.collected_on_account', $collectionId, [
            'customer_id' => $customerId,
            'amount' => Money::toString($amountMoney),
            'currency' => $currency,
            'sales_applied' => count($lines),
            'to_cash_session' => $cashSessionId,
        ]);

        return new CollectOnAccountResult(
            collectionId: $collectionId,
            customerId: $customerId,
            amount: Money::toString($amountMoney),
            currency: $currency,
            applications: $lines,
        );
    }

    /**
     * The unique index on the ledger is the last line of defence against a double booking: a loser of that
     * race gets a retryable conflict instead of a 500.
     *
     * @param callable(): list<CollectOnAccountApplication> $book
     *
     * @return list<CollectOnAccountApplication>
     */
    private function bookOnce(callable $book): array
    {
        try {
            return $book();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Este cobro ya fue registrado. Reintente para ver su resultado.');
        }
    }

    /**
     * @param list<array{saleId: string, amount: string|float}> $applications
     *
     * @return list<PlanRow>
     */
    private function planUnderLock(int $customerId, BigDecimal $amount, array $applications): array
    {
        $openSales = $this->sales->lockOpenReceivablesForCustomer($customerId);
        if ($openSales === []) {
            throw new ConflictHttpException('La deuda del cliente cambió; actualice y reintente el cobro.');
        }

        return $this->planner->plan($openSales, $amount, $applications, self::EXCESS_HINT);
    }

    /**
     * The result of a collection that was already booked under this id, or null when none was.
     * A retry after a timeout lands here, so the customer is not charged twice.
     */
    private function replay(string $collectionId, int $customerId): ?CollectOnAccountResult
    {
        $rows = $this->entries?->findCollectionApplications($collectionId) ?? [];
        if ($rows === []) {
            return null;
        }

        if ($rows[0]['customerId'] !== $customerId) {
            throw new ConflictHttpException('Esta clave de idempotencia ya se usó en otro cobro.');
        }

        $total = BigDecimal::zero();
        $lines = [];
        foreach ($rows as $row) {
            $sale = $this->sales->find($row['saleId']);
            $total = $total->plus($row['applied']);
            $lines[] = new CollectOnAccountApplication(
                saleId: $row['saleId'],
                document: $sale instanceof SaleRecord ? CollectOnAccountPlanner::documentLabel($sale) : $row['saleId'],
                applied: Money::toString(Money::money($row['applied'])),
                balanceDueAfter: $sale instanceof SaleRecord ? $sale->getBalanceDue() : '0.00',
            );
        }

        return new CollectOnAccountResult(
            collectionId: $collectionId,
            customerId: $customerId,
            amount: Money::toString($total),
            currency: $rows[0]['currency'],
            applications: $lines,
        );
    }

    private function resolveCashSession(User $createdBy, ?string $branchId): int
    {
        $cashSessionId = $this->salesWriteGateway->findOpenCashSessionIdForUser((int) $createdBy->getId(), $branchId);
        if ($cashSessionId === null) {
            throw new UnprocessableEntityHttpException('Abre tu caja para registrar cobros en efectivo.');
        }

        return $cashSessionId;
    }

    /**
     * The debt side: one `sale_payments` row, one ledger `payment` entry and one
     * `customer_payment_applications` row per sale, in the caller's transaction.
     *
     * @param list<PlanRow> $plan
     *
     * @return list<CollectOnAccountApplication>
     */
    private function applyPlan(
        array $plan,
        PaymentMethod $paymentMethod,
        DateTimeImmutable $paidAt,
        ?string $reference,
        int $customerId,
        string $currency,
        string $collectionId,
        BigDecimal $total,
        User $createdBy,
        ?string $moneyAccountId,
    ): array {
        $out = [];
        $entry = $this->ledger->recordPayment(
            customerId: $customerId,
            occurredOn: $paidAt,
            amount: Money::toString($total),
            currency: $currency,
            source: 'on_account_collect',
            sourceId: $collectionId,
            description: sprintf('Cobro a cuenta cliente #%d', $customerId),
        );
        foreach ($plan as $row) {
            $sale = $row['sale'];
            $applyAmount = Money::toString($row['amount']);
            $label = CollectOnAccountPlanner::documentLabel($sale);

            $payment = $this->salePaymentService->appendPayment(
                $sale,
                $paymentMethod,
                $applyAmount,
                $paidAt,
                $reference,
            );

            $this->entityManager->persist(new CustomerPaymentApplication(
                accountEntry: $entry,
                saleId: (string) $sale->getId(),
                appliedAmount: $applyAmount,
                currency: $currency,
                appliedAt: $paidAt,
                salePaymentId: (int) $payment->getId(),
            ));

            $out[] = new CollectOnAccountApplication(
                saleId: (string) $sale->getId(),
                document: $label,
                applied: $applyAmount,
                balanceDueAfter: $sale->getBalanceDue(),
            );
        }

        // Post to Treasury once for the whole collection, not once per payment row.
        $this->treasuryReceipts?->record(new TreasuryReceipt(
            source: MovementSource::ReceivableCollection,
            sourceId: $collectionId,
            amount: Money::toString($total),
            currency: $currency,
            paymentMethod: $paymentMethod,
            paidAt: $paidAt,
            userId: $createdBy->getId(),
            branchId: $plan[0]['sale']->getBranch()?->getId(),
            customerId: (string) $customerId,
            reference: $reference,
            description: sprintf('Cobro a cuenta cliente #%d', $customerId),
            moneyAccountId: $moneyAccountId,
        ));
        $this->entityManager->flush();

        return $out;
    }

    /** Emits one accounting event for the whole amount. */
    private function bookAccountingEvent(
        string $collectionId,
        BigDecimal $amount,
        string $currency,
        bool $isCash,
        DateTimeImmutable $paidAt,
        string $description,
    ): void {
        $this->messageBus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: $collectionId,
            kind: $isCash ? EconomicEvent::RECEIVABLE_COLLECT_CASH : EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: $paidAt->format('Y-m-d'),
            description: $description,
            total: Money::toString($amount),
            currency: $currency,
        ));
    }
}
