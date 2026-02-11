<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Context\TenantContext;
use App\Core\Service\TenantQuotaService;
use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\TaxDocument;
use App\Entity\User;
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
    private const array ENTITY_RESOURCE_MAP = [
        User::class => 'users',
        Customer::class => 'customers',
        Product::class => 'products',
        TaxDocument::class => 'invoices',
        Branch::class => 'branches',
    ];

    public function __construct(
        private TenantQuotaService $quotaService,
        private TenantContext $tenantContext,
    ) {
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        if ($this->tenantContext->getTenantName() === null) {
            return;
        }

        $entity = $args->getObject();
        $resource = self::ENTITY_RESOURCE_MAP[$entity::class] ?? null;

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
