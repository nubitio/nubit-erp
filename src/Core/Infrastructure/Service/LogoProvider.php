<?php

namespace App\Core\Infrastructure\Service;

use App\Core\Domain\Util\Constants;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class LogoProvider
{
    public function __construct(
        private Connection $connection,
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

        $baseUrl = $_ENV['S3_ENDPOINT'].'/'.$_ENV['S3_BUCKET_NAME'].'/';

        $folder = Constants::IMAGE_UPLOAD_DIR;

        $media = $this->connection->fetchAssociative(
            'SELECT path FROM media WHERE id = :id',
            ['id' => $company['logo']]
        );

        if (!$media) {
            return '';
        }

        return $baseUrl.$folder.$media['path'];
    }
}
