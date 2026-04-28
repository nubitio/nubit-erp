<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Quota;

use App\Organization\Entity\Branch;
use App\Contacts\Entity\Customer;
use App\Entity\Product;
use App\Billing\Entity\TaxDocument;
use App\Identity\Entity\User;
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
