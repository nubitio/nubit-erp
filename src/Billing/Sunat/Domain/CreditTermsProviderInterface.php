<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use DateTimeImmutable;

/**
 * What the sale actually left on credit: the net amount pending and its due date, for the
 * "forma de pago crédito" block of a SUNAT invoice (RS 193-2020/SUNAT: monto neto pendiente y
 * fechas de vencimiento por cuota). Null when the sale is unknown, so the caller keeps its fallback.
 */
interface CreditTermsProviderInterface
{
    /** @return array{amount: float, dueDate: DateTimeImmutable}|null */
    public function termsFor(?string $saleId): ?array;
}
