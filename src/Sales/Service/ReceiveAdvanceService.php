<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Sales\Dto\CustomerAdvanceReceipt;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\State\SalesWriteGateway;
use App\Treasury\Domain\MovementSource;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Receiving a customer advance (#382): money in from a customer with no target
 * sale. It books the money once — a cash-drawer movement (cash only) and a
 * `CUSTOMER_ADVANCE_RECEIVED_*` EconomicEvent (Dr Caja/Banco / Cr 122) — and
 * appends an `ADVANCE` entry on the customer ledger, moving the running balance
 * into the customer's favour. The advance is spent later by applying it to
 * sales (a separate flow); nothing here touches account 121.
 */
readonly class ReceiveAdvanceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerAccountLedger $ledger,
        private CustomerAccountEntryRepository $entries,
        private SalesWriteGateway $salesWriteGateway,
        private MessageBusInterface $messageBus,
        private OperationLog $operationLog,
        private ?TreasuryReceipts $treasuryReceipts = null,
        private ?BranchContextServiceInterface $branchContext = null,
    ) {}

    /** The receipt of a request already booked under this advance id, so a retry does not receive the money twice. */
    private function replay(?string $advanceId, int $customerId): ?CustomerAdvanceReceipt
    {
        $entry = $advanceId === null ? null : $this->entries->findBySource('advance_receipt', $advanceId);
        if ($entry === null) {
            return null;
        }
        if ($entry->getCustomerId() !== $customerId) {
            throw new ConflictHttpException('Esta clave de idempotencia ya se usó en otro anticipo.');
        }

        return new CustomerAdvanceReceipt(
            advanceId: (string) $advanceId,
            customerId: $customerId,
            amount: $entry->getCredit(),
            currency: $entry->getCurrency(),
            availableAdvance: $this->entries->availableAdvance($customerId),
        );
    }

    public function receive(
        int $customerId,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $createdBy,
        ?string $moneyAccountId = null,
        ?string $advanceId = null,
    ): CustomerAdvanceReceipt {
        $replayed = $this->replay($advanceId, $customerId);
        if ($replayed instanceof CustomerAdvanceReceipt) {
            return $replayed;
        }

        if (
            !$paymentMethod->getIsActive()
            || $paymentMethod->getSettlementBehavior() !== SettlementBehavior::COLLECTED
        ) {
            throw new UnprocessableEntityHttpException(
                'Seleccione un método de pago activo que represente dinero recibido. Crédito no es un cobro.',
            );
        }

        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto del anticipo debe ser mayor a 0.');
        }

        $branchId = $this->branchContext?->getCurrentBranchId();
        if ($this->branchContext !== null && $branchId === null) {
            throw new UnprocessableEntityHttpException('Selecciona una sucursal para registrar el anticipo.');
        }

        $isCash = $paymentMethod->getId() === PaymentMethod::CASH;
        $cashSessionId = null;
        if ($isCash) {
            $cashSessionId = $this->salesWriteGateway->findOpenCashSessionIdForUser(
                (int) $createdBy->getId(),
                $branchId,
            );
            if ($cashSessionId === null) {
                throw new UnprocessableEntityHttpException('Abre tu caja para registrar anticipos en efectivo.');
            }
        }

        $advanceId ??= Uuid::v4()->toRfc4122();
        $description = sprintf('Anticipo de cliente #%d', $customerId);
        $currency = 'PEN';

        $this->entityManager->wrapInTransaction(function () use (
            $customerId,
            $paidAt,
            $amountMoney,
            $currency,
            $advanceId,
            $description,
            $reference,
            $paymentMethod,
            $createdBy,
            $moneyAccountId,
            $branchId,
            $cashSessionId,
            $isCash,
        ): void {
            if ($cashSessionId !== null) {
                $this->salesWriteGateway->requireLockedOpenCashSessionForUser(
                    $cashSessionId,
                    (int) $createdBy->getId(),
                );
            }

            $this->ledger->recordAdvanceReceived(
                customerId: $customerId,
                occurredOn: $paidAt,
                amount: Money::toString($amountMoney),
                currency: $currency,
                source: 'advance_receipt',
                sourceId: $advanceId,
                description: $reference !== null && $reference !== '' ? $reference : $description,
                branchId: $branchId,
            );

            // Same transaction as the ledger entry: a Treasury refusal leaves no advance behind.
            $this->treasuryReceipts?->record(new TreasuryReceipt(
                source: MovementSource::CustomerAdvance,
                sourceId: $advanceId,
                amount: Money::toString($amountMoney),
                currency: $currency,
                paymentMethod: $paymentMethod,
                paidAt: $paidAt,
                userId: $createdBy->getId(),
                branchId: $branchId,
                customerId: (string) $customerId,
                reference: $reference,
                description: $description,
                moneyAccountId: $moneyAccountId,
            ));

            if ($cashSessionId !== null) {
                $this->salesWriteGateway->writeCustomerAdvanceMovement(
                    advanceId: $advanceId,
                    cashSessionId: $cashSessionId,
                    createdBy: (int) $createdBy->getId(),
                    amount: Money::toString($amountMoney),
                    paymentMethodId: PaymentMethod::CASH,
                    createdAt: $paidAt,
                    description: $description,
                );
            }

            $this->messageBus->dispatch(new EconomicEvent(
                source: EconomicEvent::SOURCE_CUSTOMER_ADVANCE,
                sourceId: $advanceId,
                kind: $isCash
                    ? EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_CASH
                    : EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_BANK,
                occurredOn: $paidAt->format('Y-m-d'),
                description: $description,
                total: Money::toString($amountMoney),
                currency: $currency,
            ));

            $this->operationLog->record('sales', 'customer_advance.received', $advanceId, [
                'customer_id' => $customerId,
                'amount' => Money::toString($amountMoney),
                'currency' => $currency,
                'to_cash_session' => $cashSessionId,
            ]);
        });

        return new CustomerAdvanceReceipt(
            advanceId: $advanceId,
            customerId: $customerId,
            amount: Money::toString($amountMoney),
            currency: $currency,
            availableAdvance: $this->entries->availableAdvance($customerId),
        );
    }
}
