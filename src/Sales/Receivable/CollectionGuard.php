<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Service\CollectionPlanGuard;
use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** What a collection request must satisfy before anything is read or locked. */
final readonly class CollectionGuard
{
    public static function assertTenders(CollectionRequest $request): void
    {
        $cash = 0;
        foreach ($request->tenders as $tender) {
            CollectionPlanGuard::assertCollectableMethod($tender->method);
            if (!Money::money($tender->amount)->isPositive()) {
                throw new UnprocessableEntityHttpException('Cada medio de pago debe tener un monto mayor a 0.');
            }
            $cash += self::isCash($tender) ? 1 : 0;
        }
        if ($cash > 1) {
            throw new UnprocessableEntityHttpException('Indica el efectivo en un solo medio de pago.');
        }
    }

    public static function total(CollectionRequest $request): BigDecimal
    {
        return array_reduce(
            $request->tenders,
            static fn(BigDecimal $carry, TenderInput $t): BigDecimal => $carry->plus(Money::money($t->amount)),
            BigDecimal::zero(),
        );
    }

    public static function hasCash(CollectionRequest $request): bool
    {
        return array_filter($request->tenders, self::isCash(...)) !== [];
    }

    private static function isCash(TenderInput $tender): bool
    {
        return $tender->method->getId() === PaymentMethod::CASH;
    }
}
