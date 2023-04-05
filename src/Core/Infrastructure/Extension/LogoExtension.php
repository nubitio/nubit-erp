<?php

namespace App\Core\Infrastructure\Extension;

use App\Core\Domain\Util\Constants;
use Doctrine\DBAL\Connection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LogoExtension extends AbstractExtension
{
    public function __construct(
        private readonly Connection $connection,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logo', [$this, 'getLogo']),
        ];
    }

    public function getLogo(): string
    {
        $company = $this->connection->fetchAssociative('SELECT logo FROM empresa LIMIT 1');

        if (!$company) {
            return '';
        }

        $baseUrl = $_ENV['S3_ENDPOINT'] . '/' . $_ENV['S3_BUCKET_NAME'] . '/';

        $folder = Constants::IMAGE_UPLOAD_DIR;

        $media = $this->connection->fetchAssociative('SELECT path FROM media WHERE id = :id', ['id' => $company['logo']]);

        if (!$media) {
            return '';
        }

        return $baseUrl . $folder . $media['path'];
    }
}
