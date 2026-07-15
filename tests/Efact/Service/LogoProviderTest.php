<?php

declare(strict_types=1);

namespace App\Tests\Efact\Service;

use App\Efact\Filesystem\S3UrlResolver;
use App\Efact\Service\LogoProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;

final class LogoProviderTest extends TestCase
{
    public function testReturnsEmptyWithoutTenantContext(): void
    {
        $tenantContext = $this->createStub(TenantContext::class);
        $tenantContext->method('getTenantName')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::never())->method('createQuery');

        $provider = new LogoProvider(
            $entityManager,
            new S3UrlResolver('https://storage.example', 'assets'),
            $tenantContext,
        );

        static::assertSame('', $provider());
    }

    public function testLoadsCompanyAndLogoWithOneDqlQuery(): void
    {
        $tenantContext = $this->createStub(TenantContext::class);
        $tenantContext->method('getTenantName')->willReturn('demo');

        $query = $this->createMock(Query::class);
        $query->expects(static::once())->method('setMaxResults')->with(1)->willReturnSelf();
        $query->expects(static::once())->method('getOneOrNullResult')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(static::once())
            ->method('createQuery')
            ->with(static::stringContains('LEFT JOIN FETCH c.logo'))
            ->willReturn($query);

        $provider = new LogoProvider(
            $entityManager,
            new S3UrlResolver('https://storage.example', 'assets'),
            $tenantContext,
        );

        static::assertSame('', $provider());
    }
}
