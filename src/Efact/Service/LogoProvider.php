<?php

declare(strict_types=1);

namespace App\Efact\Service;

use App\Core\Context\TenantContext;
use App\Core\Filesystem\S3UrlResolver;
use App\Efact\Config\EfactConstants;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class LogoProvider
{
    public function __construct(
        private Connection $connection,
        private S3UrlResolver $s3UrlResolver,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(): string
    {
        try {
            $company = $this->connection->fetchAssociative('SELECT logo FROM empresa LIMIT 1');
        } catch (Exception) {
            return '';
        }

        if (!$company) {
            return '';
        }

        try {
            $media = $this->connection->fetchAssociative(
                'SELECT path FROM media WHERE id = :id',
                ['id' => $company['logo']]
            );
        } catch (Exception) {
            return '';
        }

        if (!$media) {
            return '';
        }

        $path = $this->tenantContext->getTenantName() . '/' . EfactConstants::IMAGE_DIR . '/' . $media['path'];

        return $this->s3UrlResolver->resolve($path);
    }
}
