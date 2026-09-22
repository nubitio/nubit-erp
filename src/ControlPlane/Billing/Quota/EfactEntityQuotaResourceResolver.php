<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Quota;

use Nubit\Platform\Quota\Contract\QuotaResourceResolverInterface;
use Nubit\TenantBundle\Quota\QuotaResourceRegistry;

final readonly class EfactEntityQuotaResourceResolver implements QuotaResourceResolverInterface
{
    public function __construct(private QuotaResourceRegistry $registry) {}

    public function supports(object $subject): bool
    {
        return $this->resolveResource($subject) !== null;
    }

    public function resolveResource(object $subject): ?string
    {
        return $this->registry->resolve($subject);
    }
}
