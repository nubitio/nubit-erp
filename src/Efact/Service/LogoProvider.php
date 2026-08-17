<?php

declare(strict_types=1);

namespace App\Efact\Service;

use App\Efact\Config\EfactConstants;
use App\Efact\Filesystem\S3UrlResolver;
use App\Organization\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Scope\TenantScope;

readonly class LogoProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private S3UrlResolver $s3UrlResolver,
        private TenantContext $tenantContext,
        private FilesystemOperator $defaultFilesystem,
    ) {}

    public function __invoke(): string
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return '';
        }

        $logo = $this->getLogo();
        if ($logo === null) {
            return '';
        }

        $path = $tenantName . '/' . EfactConstants::IMAGE_DIR . '/' . $logo->getPath();

        return $this->s3UrlResolver->resolve($path);
    }

    /**
     * Returns a self-contained image source so PDF rendering does not depend on
     * WeasyPrint being able to fetch the public S3/CDN URL.
     */
    public function embedded(): string
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return '';
        }

        $logo = $this->getLogo();
        if ($logo === null) {
            return '';
        }

        try {
            $contents = $this->defaultFilesystem->read(
                TenantScope::sanitize($tenantName) . '/' . EfactConstants::IMAGE_DIR . '/' . $logo->getPath(),
            );
        } catch (\Throwable) {
            return '';
        }

        $mimeType = $logo->getMimeType();
        if ($mimeType === null || !str_starts_with($mimeType, 'image/')) {
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
    }

    private function getLogo(): ?Media
    {
        try {
            $company = $this->entityManager
                ->createQuery(<<<'DQL'
                    SELECT c, logo
                    FROM App\Organization\Entity\Company c
                    LEFT JOIN FETCH c.logo logo
                    DQL)
                ->setMaxResults(1)
                ->getOneOrNullResult();
        } catch (\Throwable) {
            return null;
        }

        return $company instanceof Company ? $company->getLogo() : null;
    }
}
