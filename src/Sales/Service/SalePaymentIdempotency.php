<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Efact\Domain\Money;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Recognises a retried single-sale collection by the key of the request that created the first payment. */
final readonly class SalePaymentIdempotency
{
    /**
     * The payment a previous request with this key already registered, or null. A key reused for another sale or
     * amount is a client bug, not a retry.
     */
    public static function existing(
        EntityManagerInterface $entityManager,
        ?string $key,
        SaleRecord $sale,
        BigDecimal $amount,
    ): ?SalePayment {
        if ($key === null) {
            return null;
        }

        $existing = $entityManager->getRepository(SalePayment::class)->findOneBy(['idempotencyKey' => $key]);
        if (!$existing instanceof SalePayment) {
            return null;
        }
        if (
            $existing->getSaleRecord()->getId() !== $sale->getId()
            || !Money::money($existing->getAmount())->isEqualTo($amount)
        ) {
            throw new ConflictHttpException('Esta clave de idempotencia ya se usó en otro cobro.');
        }

        return $existing;
    }
}
