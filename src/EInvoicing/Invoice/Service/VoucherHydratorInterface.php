<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Service;

use App\EInvoicing\Invoice\Domain\Voucher;
use App\EInvoicing\Invoice\Dto\HydratedVoucher;

interface VoucherHydratorInterface
{
    public function hydrate(Voucher $voucher): HydratedVoucher;
}
