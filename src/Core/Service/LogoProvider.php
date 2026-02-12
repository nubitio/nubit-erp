<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Core\Context\TenantContext;
use App\Core\Filesystem\S3UrlResolver;
use App\Core\Util\Constants;
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
        $company = $this->connection->fetchAssociative('SELECT logo FROM empresa LIMIT 1');

        if (!$company) {
            return '';
        }

        $media = $this->connection->fetchAssociative(
            'SELECT path FROM media WHERE id = :id',
            ['id' => $company['logo']]
        );

        if (!$media) {
            return '';
        }

        $path = Constants::IMAGE_UPLOAD_DIR . $this->tenantContext->getTenantName() . '/' . $media['path'];

        return $this->s3UrlResolver->resolve($path);
    }
}
