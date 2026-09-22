<?php

declare(strict_types=1);

namespace App\Tests\Efact\Service;

use App\Efact\Filesystem\S3UrlResolver;
use App\Organization\Service\CompanyLogoProvider;
use App\Organization\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\Platform\Filesystem\FileManager;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class LogoProviderTest extends TestCase
{
    public function testReturnsEmptyWithoutTenantContext(): void
    {
        $tenantContext = $this->createStub(TenantContext::class);
        $tenantContext->method('getTenantName')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::never())->method('getRepository');

        $provider = new CompanyLogoProvider(
            $entityManager,
            new S3UrlResolver('https://storage.example', 'assets'),
            $tenantContext,
            $this->createStub(FileManager::class),
            new NullLogger(),
        );

        static::assertSame('', $provider());
    }

    public function testReturnsEmptyWhenNoCompanyRow(): void
    {
        $tenantContext = $this->createStub(TenantContext::class);
        $tenantContext->method('getTenantName')->willReturn('demo');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('findOneBy')->with([])->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::once())->method('getRepository')->with(Company::class)->willReturn($repository);

        $provider = new CompanyLogoProvider(
            $entityManager,
            new S3UrlResolver('https://storage.example', 'assets'),
            $tenantContext,
            $this->createStub(FileManager::class),
            new NullLogger(),
        );

        static::assertSame('', $provider());
    }

    public function testReturnsEmbeddedLogoForPdfRendering(): void
    {
        $logo = new Media()
            ->setPath('logo.png')
            ->setMimeType('image/png');

        $provider = $this->providerWithLogo($logo, static fn(): string => 'png contents');

        static::assertSame('data:image/png;base64,' . base64_encode('png contents'), $provider->embedded());
    }

    public function testReturnsEmptyWhenTheLogoFileCannotBeRead(): void
    {
        $logo = new Media()
            ->setPath('logo.png')
            ->setMimeType('image/png');

        $provider = $this->providerWithLogo($logo, static function (): string {
            throw new \RuntimeException('not found');
        });

        static::assertSame('', $provider->embedded());
    }

    private function providerWithLogo(Media $logo, callable $read): CompanyLogoProvider
    {
        $tenantContext = $this->createStub(TenantContext::class);
        $tenantContext->method('getTenantName')->willReturn('demo');

        $company = new Company()->setLogo($logo);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($company);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $fileManager = $this->createStub(FileManager::class);
        $fileManager->method('read')->willReturnCallback($read);

        return new CompanyLogoProvider(
            $entityManager,
            new S3UrlResolver('https://storage.example', 'assets'),
            $tenantContext,
            $fileManager,
            new NullLogger(),
        );
    }
}
