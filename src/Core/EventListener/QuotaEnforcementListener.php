<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Context\TenantContext;
use Nubit\Platform\Quota\Contract\QuotaEnforcerInterface;
use Nubit\Platform\Quota\Contract\QuotaResourceResolverInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsDoctrineListener(event: Events::onClear)]
final readonly class QuotaEnforcementListener
{
    public function __construct(
        private QuotaEnforcerInterface $quotaService,
        private TenantContext $tenantContext,
        private QuotaResourceResolverInterface $quotaResourceResolver,
    ) {
    }

    /**
     * @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        if ($this->tenantContext->getTenantName() === null) {
            return;
        }

        $entity = $args->getObject();
        $resource = $this->quotaResourceResolver->resolveResource($entity);

        if ($resource === null) {
            return;
        }

        $this->quotaService->enforce($resource);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        unset($args);
        $this->quotaService->releaseLocks();
    }

    public function onClear(OnClearEventArgs $args): void
    {
        unset($args);
        $this->quotaService->releaseLocks();
    }
}
