<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** No account is mapped to the payment method for the branch; the movement goes to suspense and warns. */
final class AccountNotConfigured extends TreasuryException
{
    public static function for(int $paymentMethodId, string $branchId): self
    {
        return new self(sprintf(
            'El método de pago %d no tiene cuenta asignada en la sucursal %s.',
            $paymentMethodId,
            $branchId,
        ));
    }
}
