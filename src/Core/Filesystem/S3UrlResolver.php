<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class S3UrlResolver
{
    public function __construct(
        #[Autowire(env: 'S3_ENDPOINT')] private string $endpoint,
        #[Autowire(env: 'S3_BUCKET_NAME')] private string $bucket,
    ) {
    }

    public function resolve(string $path): string
    {
        return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($path, '/');
    }
}
