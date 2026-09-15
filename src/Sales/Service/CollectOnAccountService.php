<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Dto\CollectOnAccountApplication;
use App\Sales\Dto\CollectOnAccountResult;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\State\SalesWriteGateway;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRecordRepository $sales,
        private CollectOnAccountPlanner $planner,
        private SalePaymentService $salePaymentService,
        private CustomerAccountLedger $ledger,
        private SalesWriteGateway $salesWriteGateway,
        private MessageBusInterface $messageBus,
        private OperationLog $operationLog,
    ) {}

    /**
     * @param list<array{saleId: string, amount: string|float}> $applications
     *        empty = FIFO over every open sale, oldest first
     */
    public function collect(
        int $customerId,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        array $applications,
        User $createdBy,
    ): CollectOnAccountResult {
        $this->assertCollectableMethod($paymentMethod);

        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto del cobro debe ser mayor a 0.');
        }

        $openSales = $this->sales->findOpenReceivablesForCustomer($customerId);
        if ($openSales === []) {
            throw new UnprocessableEntityHttpException('El cliente no tiene comprobantes pendientes de cobro.');
        }

        $plan = $applications === []
            ? $this->planner->fifo($openSales, $amountMoney)
            : $this->planner->explicit($openSales, $applications);

        $this->assertFullyApplied($plan, $amountMoney);

        $currency = $plan[0]['sale']->getCurrency() !== '' ? $plan[0]['sale']->getCurrency() : 'PEN';
        $isCash = $paymentMethod->getId() === PaymentMethod::CASH;
        $cashSessionId = $isCash ? $this->resolveCashSession($createdBy) : null;

        $collectionId = Uuid::v4()->toRfc4122();
        $description = sprintf('Cobro a cuenta cliente #%d', $customerId);

        $lines = $this->applyPlan($plan, $paymentMethod, $paidAt, $reference, $customerId, $currency, $collectionId);

        $this->bookMoney($collectionId, $amountMoney, $currency, $cashSessionId, $paidAt, $description, $createdBy);

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

    private function assertCollectableMethod(PaymentMethod $paymentMethod): void
    {
        if (
            !$paymentMethod->getIsActive()
            || $paymentMethod->getSettlementBehavior() !== SettlementBehavior::COLLECTED
        ) {
            throw new UnprocessableEntityHttpException(
                'Seleccione un método de pago activo que represente dinero recibido. Crédito no es un cobro.',
            );
        }
    }

    /**
     * @param list<PlanRow> $plan
     */
    private function assertFullyApplied(array $plan, BigDecimal $amount): void
    {
        $applied = array_reduce(
            $plan,
            static fn(BigDecimal $carry, array $row): BigDecimal => $carry->plus($row['amount']),
            BigDecimal::zero(),
        );

        if ($applied->isLessThan($amount)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'El monto (%s) excede la deuda que se puede aplicar (%s). Ajuste el monto o las facturas; '
                . 'dejar el excedente como anticipo del cliente aún no está disponible.',
                Money::toString($amount),
                Money::toString($applied),
            ));
        }
    }

    private function resolveCashSession(User $createdBy): int
    {
        $cashSessionId = $this->salesWriteGateway->findOpenCashSessionIdForUser((int) $createdBy->getId());
        if ($cashSessionId === null) {
            throw new UnprocessableEntityHttpException('Abre tu caja para registrar cobros en efectivo.');
        }

        return $cashSessionId;
    }

    /**
     * The debt side: one `sale_payments` row, one ledger `payment` entry and one
     * `customer_payment_applications` row per sale, in a single transaction.
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
    ): array {
        return $this->entityManager->wrapInTransaction(function () use (
            $plan,
            $paymentMethod,
            $paidAt,
            $reference,
            $customerId,
            $currency,
            $collectionId,
        ): array {
            $out = [];
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

                $entry = $this->ledger->recordPayment(
                    customerId: $customerId,
                    occurredOn: $paidAt,
                    amount: $applyAmount,
                    currency: $currency,
                    saleId: (string) $sale->getId(),
                    source: 'on_account_collect',
                    sourceId: $collectionId,
                    description: $label,
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

            $this->entityManager->flush();

            return $out;
        });
    }

    /**
     * Books the money once: the cash-drawer movement (cash only) and the
     * EconomicEvent for the whole amount.
     */
    private function bookMoney(
        string $collectionId,
        BigDecimal $amount,
        string $currency,
        ?int $cashSessionId,
        DateTimeImmutable $paidAt,
        string $description,
        User $createdBy,
    ): void {
        if ($cashSessionId !== null) {
            $this->salesWriteGateway->writeOnAccountCollectionMovement(
                collectionId: $collectionId,
                cashSessionId: $cashSessionId,
                createdBy: (int) $createdBy->getId(),
                amount: Money::toString($amount),
                paymentMethodId: PaymentMethod::CASH,
                createdAt: $paidAt,
                description: $description,
            );
        }

        $this->messageBus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: $collectionId,
            kind: $cashSessionId !== null
                ? EconomicEvent::RECEIVABLE_COLLECT_CASH
                : EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: $paidAt->format('Y-m-d'),
            description: $description,
            total: Money::toString($amount),
            currency: $currency,
        ));
    }
}
