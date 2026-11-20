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

    public function receive(
        int $customerId,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $createdBy,
        ?string $moneyAccountId = null,
    ): CustomerAdvanceReceipt {
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

        $isCash = $paymentMethod->getId() === PaymentMethod::CASH;
        $cashSessionId = null;
        if ($isCash) {
            $cashSessionId = $this->salesWriteGateway->findOpenCashSessionIdForUser(
                (int) $createdBy->getId(),
                $this->branchContext?->getCurrentBranchId(),
            );
            if ($cashSessionId === null) {
                throw new UnprocessableEntityHttpException('Abre tu caja para registrar anticipos en efectivo.');
            }
        }

        $advanceId = Uuid::v4()->toRfc4122();
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
        ): void {
            $this->ledger->recordAdvanceReceived(
                customerId: $customerId,
                occurredOn: $paidAt,
                amount: Money::toString($amountMoney),
                currency: $currency,
                source: 'advance_receipt',
                sourceId: $advanceId,
                description: $reference !== null && $reference !== '' ? $reference : $description,
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
                branchId: null,
                customerId: (string) $customerId,
                reference: $reference,
                description: $description,
                moneyAccountId: $moneyAccountId,
            ));
        });

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

        return new CustomerAdvanceReceipt(
            advanceId: $advanceId,
            customerId: $customerId,
            amount: Money::toString($amountMoney),
            currency: $currency,
            availableAdvance: $this->entries->availableAdvance($customerId),
        );
    }
}
