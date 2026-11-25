<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Domain\IntegrationSaleActor;
use App\Sales\Entity\SaleRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/** Posts a verified external receipt, never a payment intent. The caller authenticates the provider and branch. */
final readonly class ExternalBankCollection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SalePaymentService $payments,
        private ExternalReceiptPosting $posting,
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
        bool $gateway = false,
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
                $gateway,
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
                $existing = $this->entityManager->getConnection()->fetchAssociative(<<<'SQL'
                    SELECT p.id, p.sale_id, p.payment_method_id, p.amount, p.paid_at, a.kind
                    FROM sale_payments p
                    LEFT JOIN treasury_movement m ON m.source_type = 'sale_payment' AND m.source_id = p.idempotency_key AND m.leg = 'main'
                    LEFT JOIN treasury_account a ON a.id = m.account_id
                    WHERE p.idempotency_key = ?
                    SQL, [
                    $key,
                ]);
                if ($existing !== false) {
                    $recorded = [
                        $existing['sale_id'],
                        (int) $existing['payment_method_id'],
                        $existing['amount'],
                        $existing['paid_at'],
                        $existing['kind'],
                    ];
                    if (
                        $allowReplay
                        && $recorded === [
                            $saleId,
                            $paymentMethodId,
                            $amount,
                            $paidAt->format('Y-m-d H:i:s'),
                            $gateway ? 'clearing' : 'bank',
                        ]
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

                return $this->posting->book(
                    $sale,
                    $payment,
                    $branchId,
                    $paymentMethodId,
                    $amount,
                    $paidAt,
                    $reference,
                    $gateway,
                );
            });
        } catch (UniqueConstraintViolationException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'uq_sale_payments_idempotency_key')) {
                throw $e;
            }
            throw new ConflictHttpException('Esta referencia de cobro ya se registró.', $e);
        }
    }
}
