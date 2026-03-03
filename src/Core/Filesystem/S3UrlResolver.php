<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

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
 *    When a CDN or vanity domain is used it already points to the bucket root,
 *    so the bucket name must NOT be appended to the path. Set S3_PUBLIC_URL to
 *    the CDN base (e.g. https://assets.efact.app) and leave the bucket name only
 *    in S3_BUCKET_NAME (used solely by Flysystem for write operations).
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
        if ($publicUrl !== '') {
            // CDN / custom-domain mode: the base URL already points at the
            // bucket root, so we must NOT include the bucket name in the path.
            $this->base = $publicUrl;
            $this->prependBucket = false;
        } else {
            // Direct S3 path-style URL: endpoint + bucket + path.
            $this->base = $endpoint;
            $this->prependBucket = true;
        }
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
