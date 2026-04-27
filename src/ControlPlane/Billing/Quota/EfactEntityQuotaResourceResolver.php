<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Quota;

use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\TaxDocument;
use App\Entity\User;
use Nubit\Platform\Quota\Contract\QuotaResourceResolverInterface;

final readonly class EfactEntityQuotaResourceResolver implements QuotaResourceResolverInterface
{
    private const array ENTITY_RESOURCE_MAP = [
        User::class => 'users',
        Customer::class => 'customers',
        Product::class => 'products',
        TaxDocument::class => 'invoices',
        Branch::class => 'branches',
    ];

    public function supports(object $subject): bool
    {
        return isset(self::ENTITY_RESOURCE_MAP[$subject::class]);
    }

    public function resolveResource(object $subject): ?string
    {
        return self::ENTITY_RESOURCE_MAP[$subject::class] ?? null;
    }
}
