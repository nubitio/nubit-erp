<?php

declare(strict_types=1);

namespace App\Tests\AI\Service;

use App\AI\Service\SessionContextBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nubit\AdminBundle\Auth\JWTManager;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class SessionContextBuilderTest extends TestCase
{
    public function testResolvesCashSessionWithDql(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects(static::once())->method('setParameter')->with('branchId', 'branch-1')->willReturnSelf();
        $query->expects(static::once())->method('setMaxResults')->with(1)->willReturnSelf();
        $query->expects(static::once())->method('getOneOrNullResult')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(static::once())
            ->method('createQuery')
            ->with(static::stringContains('FROM App\\CashRegister\\Entity\\CashSession cs'))
            ->willReturn($query);

        $builder = new SessionContextBuilder(
            $this->createStub(Security::class),
            new \ReflectionClass(JWTManager::class)->newInstanceWithoutConstructor(),
            $this->createStub(TenantContext::class),
            $entityManager,
        );

        $method = new \ReflectionMethod(SessionContextBuilder::class, 'resolveCashSession');

        static::assertSame([null, 'NONE'], $method->invoke($builder, 'branch-1'));
    }
}
