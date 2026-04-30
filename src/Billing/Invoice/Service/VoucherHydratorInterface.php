<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Service;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Dto\HydratedVoucher;

interface VoucherHydratorInterface
{
    public function hydrate(Voucher $voucher): HydratedVoucher;
}
