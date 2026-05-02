<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inventory\Entity\TenantInventoryPolicy;
use App\Inventory\Repository\TenantInventoryPolicyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Override;

/**
 * Upserts the inventory policy for the current tenant.
 *
 * If a policy row already exists it updates it; otherwise it creates one.
 * The tenant_id is always taken from the JWT context — the client cannot
 * set it.
 *
 * @implements ProcessorInterface<TenantInventoryPolicy, TenantInventoryPolicy>
 */
final readonly class TenantInventoryPolicyProcessor implements ProcessorInterface
{
    public function __construct(
        private TenantInventoryPolicyRepository $repository,
        private EntityManagerInterface $em,
        private ?TenantContext $tenantContext = null,
    ) {
    }

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TenantInventoryPolicy
    {
        if (!$data instanceof TenantInventoryPolicy) {
            throw new \InvalidArgumentException('Expected TenantInventoryPolicy');
        }

        $tenantId = (string) ($this->tenantContext?->getTenantId() ?? 'default');

        // Upsert: find existing row or use the submitted instance
        $existing = $this->repository->findOneBy(['tenantId' => $tenantId]);

        if ($existing !== null) {
            $existing->setPolicy($data->getPolicy());
            $existing->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            return $existing;
        }

        $data->setTenantId($tenantId);
        $data->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
