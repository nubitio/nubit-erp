<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Domain\IntegrationSaleActor;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\Party;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\RecordsMoneyMovement;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/** Posts a verified external receipt, never a payment intent. The caller authenticates the provider and branch. */
final readonly class ExternalBankCollection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SalePaymentService $payments,
        private RecordsMoneyMovement $treasury,
        private MessageBusInterface $bus,
    ) {}

    public function post(
        string $saleId,
        IntegrationSaleActor $actor,
        string $branchId,
        int $paymentMethodId,
        string $amount,
        string $currency,
        DateTimeImmutable $paidAt,
        string $captureReference,
        bool $allowReplay = false,
    ): int {
        $reference = trim($captureReference);
        if ($paymentMethodId === PaymentMethod::CASH || $reference === '' || strlen($reference) > 120) {
            throw new UnprocessableEntityHttpException('Indica un cobro no efectivo y una referencia externa válida.');
        }
        $amount = Money::toString(Money::money($amount));
        if (!Money::money($amount)->isGreaterThan(0)) {
            throw new UnprocessableEntityHttpException('El monto del cobro debe ser mayor a cero.');
        }
        $key = Uuid::v5(Uuid::fromString($actor->clientId), 'external-bank-capture:' . $reference)->toRfc4122();

        try {
            return $this->entityManager->wrapInTransaction(function () use (
                $saleId,
                $actor,
                $branchId,
                $paymentMethodId,
                $amount,
                $currency,
                $paidAt,
                $reference,
                $key,
                $allowReplay,
            ): int {
                $sale = $this->entityManager->find(SaleRecord::class, $saleId, LockMode::PESSIMISTIC_WRITE);
                if (
                    !$sale instanceof SaleRecord
                    || $sale->getIntegrationClientId() !== $actor->clientId
                    || $sale->getBranch()?->getId() !== $branchId
                ) {
                    throw new NotFoundHttpException('No existe una venta de esta integración en la sucursal.');
                }
                if ($sale->getCurrency() !== $currency) {
                    throw new UnprocessableEntityHttpException(
                        'La moneda del cobro debe coincidir con la de la venta.',
                    );
                }
                $existing = $this->entityManager->getConnection()->fetchAssociative('SELECT id, sale_id, payment_method_id, amount, paid_at FROM sale_payments WHERE idempotency_key = ?', [
                    $key,
                ]);
                if ($existing !== false) {
                    $recorded = [
                        $existing['sale_id'],
                        (int) $existing['payment_method_id'],
                        $existing['amount'],
                        $existing['paid_at'],
                    ];
                    if (
                        $allowReplay
                        && $recorded === [$saleId, $paymentMethodId, $amount, $paidAt->format('Y-m-d H:i:s')]
                    ) {
                        return (int) $existing['id'];
                    }

                    throw new ConflictHttpException('Esta referencia de cobro ya se registró.');
                }
                $method = $this->entityManager->find(PaymentMethod::class, $paymentMethodId);
                if (!$method instanceof PaymentMethod) {
                    throw new UnprocessableEntityHttpException('El medio de pago no existe.');
                }

                $payment = $this->payments->appendExternalPayment($sale, $method, $amount, $paidAt, $reference, $key);

                return $this->book($sale, $payment, $branchId, $paymentMethodId, $amount, $paidAt, $reference);
            });
        } catch (UniqueConstraintViolationException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'uq_sale_payments_idempotency_key')) {
                throw $e;
            }
            throw new ConflictHttpException('Esta referencia de cobro ya se registró.', $e);
        }
    }

    private function book(
        SaleRecord $sale,
        SalePayment $payment,
        string $branchId,
        int $methodId,
        string $amount,
        DateTimeImmutable $paidAt,
        string $reference,
    ): int {
        $id = $payment->getId() ?? throw new \LogicException('The payment must be stored before posting.');
        $captureKey = $payment->getIdempotencyKey() ?? throw new \LogicException('The payment has no capture key.');
        $description = 'Cobro externo ' . $reference;
        $customerId = $sale->getCustomer()?->getId();
        try {
            $movement = $this->treasury->record(new MoneyMovementRequest(
                key: new MovementKey(MovementSource::SalePayment, $captureKey),
                direction: Direction::In,
                amount: $amount,
                currency: $payment->getCurrency(),
                branchId: $branchId,
                occurredAt: $paidAt,
                paymentMethodId: $methodId,
                party: $customerId === null ? null : new Party('customer', (string) $customerId),
                reference: $reference,
                description: $description,
                requireMappedBankAccount: true,
            ));
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
        if ($movement === null || $movement->duplicate) {
            throw new UnprocessableEntityHttpException('No se pudo registrar un nuevo cobro bancario en Tesorería.');
        }

        $this->bus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: (string) $id,
            kind: EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: $paidAt->format('Y-m-d'),
            description: $description,
            total: $amount,
            currency: $payment->getCurrency(),
        ));

        return $id;
    }
}
