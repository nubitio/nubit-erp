<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves public URLs for S3-stored assets.
 *
 * By default builds URLs directly against the S3 endpoint.
 * Set S3_PUBLIC_URL to a CDN/proxy base (e.g. https://assets.efact.app)
 * to serve assets through Cloudflare or any other CDN without changing
 * any application code — just swap the env var.
 *
 * URL format: {base}/{bucket}/{path}
 */
final readonly class S3UrlResolver
{
    private string $base;

    public function __construct(
        #[Autowire(env: 'S3_ENDPOINT')] string $endpoint,
        #[Autowire(env: 'S3_BUCKET_NAME')] private string $bucket,
        #[Autowire(env: 'S3_PUBLIC_URL')] string $publicUrl = '',
    ) {
        // If a dedicated public/CDN URL is configured, use it as the base.
        // Otherwise fall back to the raw S3 endpoint.
        $this->base = $publicUrl !== '' ? $publicUrl : $endpoint;
    }

    public function resolve(string $path): string
    {
        return rtrim($this->base, '/') . '/' . $this->bucket . '/' . ltrim($path, '/');
    }
}
