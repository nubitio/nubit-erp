<?php

declare(strict_types=1);

namespace App\AccountsPayable\Service;

use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Entity\SupplierPayment;
use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Recognises a retried supplier payment by the key of the request that created the first one. */
final readonly class SupplierPaymentIdempotency
{
    /**
     * The payment a previous request with this key already registered, or null. A key reused for another bill
     * or amount is a client bug, not a retry.
     */
    public static function existing(
        EntityManagerInterface $entityManager,
        ?string $key,
        SupplierBill $bill,
        BigDecimal $amount,
    ): ?SupplierPayment {
        if ($key === null) {
            return null;
        }

        $existing = $entityManager->getRepository(SupplierPayment::class)->findOneBy(['idempotencyKey' => $key]);
        if (!$existing instanceof SupplierPayment) {
            return null;
        }
        if (
            $existing->getSupplierBill()->getId() !== $bill->getId()
            || !Money::money($existing->getAmount())->isEqualTo($amount)
        ) {
            throw new ConflictHttpException('Esta clave de idempotencia ya se usó en otro pago.');
        }

        return $existing;
    }
}
