<?php

declare(strict_types=1);

namespace App\Organization\Service;

use App\Efact\Config\EfactConstants;
use App\Efact\Contract\LogoProviderInterface;
use App\Efact\Filesystem\S3UrlResolver;
use App\Organization\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\Platform\Filesystem\FileManager;
use Nubit\Platform\Tenant\Context\TenantContext;
use Psr\Log\LoggerInterface;

readonly class CompanyLogoProvider implements LogoProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private S3UrlResolver $s3UrlResolver,
        private TenantContext $tenantContext,
        private FileManager $fileManager,
        private LoggerInterface $logger,
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
     * Returns a self-contained image source (data: URI) so PDF/ticket rendering
     * does not depend on the renderer being able to fetch the public S3/CDN URL.
     *
     * Reads through the same {@see FileManager} the media library uploads with,
     * so the storage path (tenant prefix + media directory) always matches.
     */
    public function embedded(): string
    {
        if ($this->tenantContext->getTenantName() === null) {
            return '';
        }

        $logo = $this->getLogo();
        if ($logo === null) {
            return '';
        }

        $location = EfactConstants::IMAGE_DIR . '/' . $logo->getPath();

        try {
            $contents = $this->fileManager->read($location);
        } catch (\Throwable $exception) {
            $this->logger->warning('LogoProvider: could not read the company logo for embedding', [
                'location' => $location,
                'media_path' => $logo->getPath(),
                'exception' => $exception->getMessage(),
            ]);

            return '';
        }

        $mimeType = $logo->getMimeType();
        if ($mimeType === null || !str_starts_with($mimeType, 'image/')) {
            $mimeType = new \finfo(FILEINFO_MIME_TYPE)->buffer($contents) ?: 'application/octet-stream';
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
    }

    private function getLogo(): ?Media
    {
        try {
            $company = $this->entityManager->getRepository(Company::class)->findOneBy([]);
        } catch (\Throwable) {
            return null;
        }

        // `logo` is lazy-loaded on access. A `JOIN FETCH` here would save a
        // query, but the raw-DQL form of it fails to parse in this project
        // ("Class 'FETCH' is not defined"), which silently returned no logo on
        // every ticket/A4 PDF.
        return $company instanceof Company ? $company->getLogo() : null;
    }
}
