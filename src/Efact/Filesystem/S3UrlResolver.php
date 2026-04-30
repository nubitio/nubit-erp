<?php

declare(strict_types=1);

namespace App\Efact\Filesystem;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves public URLs for S3-stored assets.
 *
 * Two URL modes are supported:
 *
 * 1. Direct S3 (S3_PUBLIC_URL is empty):
 *    URL = {S3_ENDPOINT}/{S3_BUCKET_NAME}/{path}
 *    e.g. https://s3.us-central-1.wasabisys.com/my-bucket/tenant/images/logo.png
 *
 * 2. CDN / custom domain (S3_PUBLIC_URL is set):
 *    URL = {S3_PUBLIC_URL}/{path}
 *    e.g. https://assets.efact.app/tenant/images/logo.png
 *
 * 3. Misconfiguration fallback (S3_PUBLIC_URL is empty and bucket is a domain):
 *    URL = https://{S3_BUCKET_NAME}/{path}
 *    e.g. https://s3.efact.app/tenant/images/logo.png
 */
final readonly class S3UrlResolver
{
    private string $base;
    private bool $prependBucket;

    public function __construct(
        #[Autowire(env: 'S3_ENDPOINT')] string $endpoint,
        #[Autowire(env: 'S3_BUCKET_NAME')] private string $bucket,
        #[Autowire(env: 'S3_PUBLIC_URL')] string $publicUrl = '',
    ) {
        $publicUrl = trim($publicUrl);

        if ($publicUrl !== '') {
            $this->base = $publicUrl;
            $this->prependBucket = false;

            return;
        }

        if (str_contains($this->bucket, '.') && !str_contains($this->bucket, '/')) {
            $this->base = 'https://' . $this->bucket;
            $this->prependBucket = false;

            return;
        }

        $this->base = $endpoint;
        $this->prependBucket = true;
    }

    public function resolve(string $path): string
    {
        $base = rtrim($this->base, '/');
        $path = ltrim($path, '/');

        if ($this->prependBucket) {
            return $base . '/' . $this->bucket . '/' . $path;
        }

        return $base . '/' . $path;
    }
}
