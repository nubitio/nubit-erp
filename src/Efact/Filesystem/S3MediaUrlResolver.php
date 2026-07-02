<?php

declare(strict_types=1);

namespace App\Efact\Filesystem;

use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\AdminBundle\Media\MediaUrlResolverInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves media to direct S3/CDN URLs instead of the bundle's PHP streaming
 * route: assets are public objects served straight from the bucket. The
 * object key mirrors what the tenant-aware FileManager wrote:
 * {tenant}/{media dir}/{filename}.
 */
final readonly class S3MediaUrlResolver implements MediaUrlResolverInterface
{
    public function __construct(
        private S3UrlResolver $s3UrlResolver,
        private TenantContext $tenantContext,
        #[Autowire(param: 'nubit_admin.media.directory')]
        private string $mediaDirectory,
    ) {}

    public function resolve(Media $media): string
    {
        $path = $this->mediaDirectory . '/' . $media->getPath();

        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName !== null) {
            $path = $tenantName . '/' . $path;
        }

        return $this->s3UrlResolver->resolve($path);
    }
}
