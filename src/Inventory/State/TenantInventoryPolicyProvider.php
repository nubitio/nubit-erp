<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Entity\TenantInventoryPolicy;
use App\Inventory\Core\Enum\StockPolicy;
use App\Inventory\Repository\TenantInventoryPolicyRepository;
use Nubit\Platform\Tenant\Context\TenantContext;
use Override;

/**
 * Returns the current tenant's inventory policy.
 * If no policy row exists yet, returns a default STRICT instance (not persisted).
 *
 * @implements ProviderInterface<TenantInventoryPolicy>
 */
final readonly class TenantInventoryPolicyProvider implements ProviderInterface
{
    public function __construct(
        private TenantInventoryPolicyRepository $repository,
        private ?TenantContext $tenantContext = null,
    ) {
    }

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TenantInventoryPolicy
    {
        $tenantId = (string) ($this->tenantContext?->getTenantId() ?? 'default');

        $policy = $this->repository->findOneBy(['tenantId' => $tenantId]);

        if ($policy === null) {
            $policy = new TenantInventoryPolicy();
            $policy->setTenantId($tenantId);
            $policy->setPolicy(StockPolicy::STRICT);
            $policy->setCreatedAt(new \DateTimeImmutable());
        }

        return $policy;
    }
}
