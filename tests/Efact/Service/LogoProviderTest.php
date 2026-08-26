<?php

declare(strict_types=1);

namespace App\Tests\Efact\Service;

use App\Efact\Filesystem\S3UrlResolver;
use App\Efact\Service\LogoProvider;
use App\Organization\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use League\Flysystem\FilesystemOperator;
use Nubit\AdminBundle\Media\Entity\Media;
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
            $this->createStub(FilesystemOperator::class),
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
            $this->createStub(FilesystemOperator::class),
        );

        static::assertSame('', $provider());
    }

    public function testReturnsEmbeddedLogoForPdfRendering(): void
    {
        $tenantContext = $this->createStub(TenantContext::class);
        $tenantContext->method('getTenantName')->willReturn('demo');

        $logo = new Media()
            ->setPath('logo.png')
            ->setMimeType('image/png');
        $company = new Company()->setLogo($logo);

        $query = $this->createMock(Query::class);
        $query->method('setMaxResults')->with(1)->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn($company);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturn($query);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(static::once())->method('read')->with('demo/images/logo.png')->willReturn('png contents');

        $provider = new LogoProvider(
            $entityManager,
            new S3UrlResolver('https://storage.example', 'assets'),
            $tenantContext,
            $filesystem,
        );

        static::assertSame('data:image/png;base64,' . base64_encode('png contents'), $provider->embedded());
    }
}
