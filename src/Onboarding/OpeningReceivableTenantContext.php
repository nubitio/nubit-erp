<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Catalog\Entity\PaymentMethod;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;

/**
 * The branch/warehouse/payment-method a batch of opening-balance rows is
 * attached to. Re-resolved after every {@see \Doctrine\ORM\EntityManagerInterface::clear()}
 * checkpoint, since clear() detaches these references.
 */
final readonly class OpeningReceivableTenantContext
{
    public function __construct(
        public Branch $branch,
        public Warehouse $warehouse,
        public ?PaymentMethod $paymentMethod,
    ) {}
}
