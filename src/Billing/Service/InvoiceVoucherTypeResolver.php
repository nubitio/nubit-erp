<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Sales\Domain\DocType;
use App\Sales\Domain\RequestedVoucherType;
use App\Settings\Enum\TaxRegime;

final class InvoiceVoucherTypeResolver
{
    public static function resolve(
        TaxRegime $taxRegime,
        ?RequestedVoucherType $requestedType,
        string $customerRuc,
    ): string {
        return match (true) {
            $taxRegime === TaxRegime::NRUS => DocType::BOLETA->value,
            $requestedType === RequestedVoucherType::FACTURA => DocType::FACTURA->value,
            $requestedType === RequestedVoucherType::BOLETA => DocType::BOLETA->value,
            strlen($customerRuc) === 11 => DocType::FACTURA->value,
            default => DocType::BOLETA->value,
        };
    }
}
