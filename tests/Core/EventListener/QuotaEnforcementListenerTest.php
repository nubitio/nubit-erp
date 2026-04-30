<?php

declare(strict_types=1);

namespace App\Tests\Core\EventListener;

use Nubit\Platform\Tenant\Context\TenantContext;
use App\Core\EventListener\QuotaEnforcementListener;
use Nubit\Platform\Quota\Contract\QuotaEnforcerInterface;
use Nubit\Platform\Quota\Contract\QuotaResourceResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;

final class QuotaEnforcementListenerTest extends TestCase
{
    public function testPrePersistReturnsEarlyWithoutTenantContext(): void
    {
        $enforcer = $this->createMock(QuotaEnforcerInterface::class);
        $enforcer->expects(self::never())->method('enforce');

        $resolver = $this->createMock(QuotaResourceResolverInterface::class);
        $resolver->expects(self::never())->method('resolveResource');

        $listener = new QuotaEnforcementListener($enforcer, new TenantContext(), $resolver);

        $listener->prePersist($this->lifecycleArgs(new \stdClass()));
    }

    public function testPrePersistIgnoresUnsupportedEntities(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, null);
        $entity = new \stdClass();

        $enforcer = $this->createMock(QuotaEnforcerInterface::class);
        $enforcer->expects(self::never())->method('enforce');

        $resolver = $this->createMock(QuotaResourceResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolveResource')
            ->with($entity)
            ->willReturn(null);

        $listener = new QuotaEnforcementListener($enforcer, $tenantContext, $resolver);

        $listener->prePersist($this->lifecycleArgs($entity));
    }

    public function testPrePersistEnforcesResolvedResource(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, null);
        $entity = new \stdClass();

        $enforcer = $this->createMock(QuotaEnforcerInterface::class);
        $enforcer->expects(self::once())->method('enforce')->with('users');

        $resolver = $this->createMock(QuotaResourceResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolveResource')
            ->with($entity)
            ->willReturn('users');

        $listener = new QuotaEnforcementListener($enforcer, $tenantContext, $resolver);

        $listener->prePersist($this->lifecycleArgs($entity));
    }

    public function testPostFlushReleasesQuotaLocks(): void
    {
        $enforcer = $this->createMock(QuotaEnforcerInterface::class);
        $enforcer->expects(self::once())->method('releaseLocks');

        $resolver = $this->createMock(QuotaResourceResolverInterface::class);
        $resolver->expects(self::never())->method('resolveResource');

        $listener = new QuotaEnforcementListener($enforcer, new TenantContext(), $resolver);

        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
    }

    public function testOnClearReleasesQuotaLocks(): void
    {
        $enforcer = $this->createMock(QuotaEnforcerInterface::class);
        $enforcer->expects(self::once())->method('releaseLocks');

        $resolver = $this->createMock(QuotaResourceResolverInterface::class);
        $resolver->expects(self::never())->method('resolveResource');

        $listener = new QuotaEnforcementListener($enforcer, new TenantContext(), $resolver);

        $listener->onClear(new OnClearEventArgs($this->createStub(EntityManagerInterface::class)));
    }

    /** @return LifecycleEventArgs<EntityManagerInterface> */
    private function lifecycleArgs(object $entity): LifecycleEventArgs
    {
        $args = $this->createStub(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($entity);

        return $args;
    }
}
