<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Sales\Dto\AdvanceApplicationResult;
use App\Sales\Dto\CollectOnAccountApplication;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Applying a customer's available advance to their open sales (#382). No new
 * money moves — it entered when the advance was received. Per settled sale it
 * writes a `sale_payments` row (via {@see SalePaymentService::appendAdvancePayment()},
 * method "Anticipo aplicado") and one or more `customer_payment_applications`
 * rows drawing FIFO from the `ADVANCE` ledger entries. One
 * `CUSTOMER_ADVANCE_APPLIED` EconomicEvent posts Dr 122 / Cr 121 for the whole
 * amount. The ledger `running_balance` is untouched.
 *
 * @phpstan-import-type PlanRow from CollectOnAccountPlanner
 */
readonly class ApplyAdvanceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRecordRepository $sales,
        private CustomerAccountEntryRepository $entries,
        private CollectOnAccountPlanner $planner,
        private SalePaymentService $salePaymentService,
        private MessageBusInterface $messageBus,
        private OperationLog $operationLog,
    ) {}

    /**
     * @param list<array{saleId: string, amount: string|float}> $applications empty = FIFO
     */
    public function apply(
        int $customerId,
        string $amount,
        DateTimeImmutable $appliedAt,
        array $applications,
    ): AdvanceApplicationResult {
        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto a aplicar debe ser mayor a 0.');
        }

        $available = Money::money($this->entries->availableAdvance($customerId));
        if ($amountMoney->isGreaterThan($available)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'El anticipo disponible (%s) no alcanza para aplicar %s.',
                Money::toString($available),
                Money::toString($amountMoney),
            ));
        }

        $advanceMethod = $this->advanceMethod();

        $openSales = $this->sales->findOpenReceivablesForCustomer($customerId);
        if ($openSales === []) {
            throw new UnprocessableEntityHttpException('El cliente no tiene comprobantes pendientes de cobro.');
        }

        $plan = $applications === []
            ? $this->planner->fifo($openSales, $amountMoney)
            : $this->planner->explicit($openSales, $applications);
        $this->assertFullyApplied($plan, $amountMoney);

        $currency = $plan[0]['sale']->getCurrency() !== '' ? $plan[0]['sale']->getCurrency() : 'PEN';
        $applicationId = Uuid::v4()->toRfc4122();

        $lines = $this->applyPlan($plan, $customerId, $advanceMethod, $appliedAt, $currency);

        $description = sprintf('Aplicación de anticipo cliente #%d', $customerId);
        $this->messageBus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_CUSTOMER_ADVANCE,
            sourceId: $applicationId,
            kind: EconomicEvent::CUSTOMER_ADVANCE_APPLIED,
            occurredOn: $appliedAt->format('Y-m-d'),
            description: $description,
            total: Money::toString($amountMoney),
            currency: $currency,
        ));

        $this->operationLog->record('sales', 'customer_advance.applied', $applicationId, [
            'customer_id' => $customerId,
            'amount' => Money::toString($amountMoney),
            'currency' => $currency,
            'sales_applied' => count($lines),
        ]);

        return new AdvanceApplicationResult(
            applicationId: $applicationId,
            customerId: $customerId,
            applied: Money::toString($amountMoney),
            currency: $currency,
            availableAdvance: $this->entries->availableAdvance($customerId),
            applications: $lines,
        );
    }

    private function advanceMethod(): PaymentMethod
    {
        // Looked up by behaviour, not is_active: it is an inactive internal
        // method (hidden from the POS / Ventas pickers).
        $method = $this->entityManager
            ->getRepository(PaymentMethod::class)
            ->findOneBy(['settlementBehavior' => SettlementBehavior::ADVANCE]);
        if (!$method instanceof PaymentMethod) {
            throw new UnprocessableEntityHttpException('Falta configurar el método "Anticipo aplicado".');
        }

        return $method;
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
                'El monto (%s) excede la deuda que se puede aplicar (%s). Ajuste el monto o las facturas.',
                Money::toString($amount),
                Money::toString($applied),
            ));
        }
    }

    /**
     * @param list<PlanRow> $plan
     *
     * @return list<CollectOnAccountApplication>
     */
    private function applyPlan(
        array $plan,
        int $customerId,
        PaymentMethod $advanceMethod,
        DateTimeImmutable $appliedAt,
        string $currency,
    ): array {
        $advances = $this->entries->advancesWithRemaining($customerId);

        return $this->entityManager->wrapInTransaction(function () use (
            $plan,
            $advanceMethod,
            $appliedAt,
            $currency,
            &$advances,
        ): array {
            $out = [];
            $cursor = 0;
            foreach ($plan as $row) {
                $sale = $row['sale'];
                $amountMoney = $row['amount'];
                $applyAmount = Money::toString($amountMoney);
                $label = CollectOnAccountPlanner::documentLabel($sale);

                $payment = $this->salePaymentService->appendAdvancePayment(
                    $sale,
                    $advanceMethod,
                    $applyAmount,
                    $appliedAt,
                    $label,
                );

                $cursor = $this->allocateFromAdvances(
                    $advances,
                    $cursor,
                    $amountMoney,
                    (string) $sale->getId(),
                    (int) $payment->getId(),
                    $currency,
                    $appliedAt,
                );

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
     * Draws $amount from the advance entries in $advances starting at $cursor,
     * persisting a `customer_payment_applications` row per portion. Returns the
     * new cursor.
     *
     * @param list<array{entryId: int, remaining: string}> $advances
     */
    private function allocateFromAdvances(
        array &$advances,
        int $cursor,
        BigDecimal $amount,
        string $saleId,
        int $salePaymentId,
        string $currency,
        DateTimeImmutable $appliedAt,
    ): int {
        $left = $amount;
        while ($left->isGreaterThan(0)) {
            if (!isset($advances[$cursor])) {
                throw new UnprocessableEntityHttpException('El anticipo disponible cambió; reintente la aplicación.');
            }

            $remaining = Money::money($advances[$cursor]['remaining']);
            $portion = $left->isLessThan($remaining) ? $left : $remaining;

            $advanceEntry = $this->entityManager->getReference(
                CustomerAccountEntry::class,
                $advances[$cursor]['entryId'],
            );
            if (!$advanceEntry instanceof CustomerAccountEntry) {
                throw new UnprocessableEntityHttpException('El anticipo referenciado no existe.');
            }

            $this->entityManager->persist(new CustomerPaymentApplication(
                accountEntry: $advanceEntry,
                saleId: $saleId,
                appliedAmount: Money::toString($portion),
                currency: $currency,
                appliedAt: $appliedAt,
                salePaymentId: $salePaymentId,
            ));

            $advances[$cursor]['remaining'] = Money::toString($remaining->minus($portion));
            $left = $left->minus($portion);
            if (Money::money($advances[$cursor]['remaining'])->isLessThanOrEqualTo(0)) {
                $cursor++;
            }
        }

        return $cursor;
    }
}
