<?php

declare(strict_types=1);

namespace App\Tests\Core\Filesystem;

use App\Core\Filesystem\S3UrlResolver;
use PHPUnit\Framework\TestCase;

final class S3UrlResolverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Mode 1: direct S3 (no S3_PUBLIC_URL)
    // URL = {endpoint}/{bucket}/{path}
    // -------------------------------------------------------------------------

    public function testDirectS3AppendsBucketAndPath(): void
    {
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com',
            bucket: 'my-bucket',
            publicUrl: '',
        );

        $this->assertSame(
            'https://s3.us-central-1.wasabisys.com/my-bucket/tenant/images/logo.png',
            $resolver->resolve('tenant/images/logo.png'),
        );
    }

    public function testDirectS3StripsLeadingSlashFromPath(): void
    {
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com',
            bucket: 'my-bucket',
            publicUrl: '',
        );

        $this->assertSame(
            'https://s3.us-central-1.wasabisys.com/my-bucket/tenant/images/logo.png',
            $resolver->resolve('/tenant/images/logo.png'),
        );
    }

    public function testDirectS3StripsTrailingSlashFromEndpoint(): void
    {
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com/',
            bucket: 'my-bucket',
            publicUrl: '',
        );

        $this->assertSame(
            'https://s3.us-central-1.wasabisys.com/my-bucket/tenant/images/logo.png',
            $resolver->resolve('tenant/images/logo.png'),
        );
    }

    // -------------------------------------------------------------------------
    // Mode 2: CDN / custom-domain (S3_PUBLIC_URL is set)
    // URL = {publicUrl}/{path}   — bucket name is NOT in the path
    // -------------------------------------------------------------------------

    public function testCdnModeDoesNotAppendBucketName(): void
    {
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com',
            bucket: 'my-bucket',
            publicUrl: 'https://assets.efact.app',
        );

        $this->assertSame(
            'https://assets.efact.app/tenant/images/logo.png',
            $resolver->resolve('tenant/images/logo.png'),
        );
    }

    /**
     * Regression: production had S3_PUBLIC_URL unset and S3_BUCKET_NAME set to
     * the public domain ("s3.efact.app"), which caused the domain to appear as
     * the bucket segment in the URL:
     *
     *   https://s3.us-central-1.wasabisys.com/s3.efact.app/tenant/images/logo.png  ❌
     *
     * The correct setup is S3_PUBLIC_URL=https://s3.efact.app with the real
     * bucket name in S3_BUCKET_NAME.
     */
    public function testCdnModeWithVanityDomainBucketProducesCleanUrl(): void
    {
        // Correct configuration: S3_PUBLIC_URL = vanity domain, bucket = real name
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com',
            bucket: 'efact-prod',
            publicUrl: 'https://s3.efact.app',
        );

        $this->assertSame(
            'https://s3.efact.app/altofilete/images/logo-altofilete-69a75c4d474e5.png',
            $resolver->resolve('altofilete/images/logo-altofilete-69a75c4d474e5.png'),
        );
    }


    public function testDomainBucketFallbackBuildsVanityUrlWhenPublicUrlIsMissing(): void
    {
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com',
            bucket: 's3.efact.app',
            publicUrl: '',
        );

        $this->assertSame(
            'https://s3.efact.app/altofilete/images/logo-altofilete-69a75c4d474e5.png',
            $resolver->resolve('altofilete/images/logo-altofilete-69a75c4d474e5.png'),
        );
    }

    public function testCdnModeStripsLeadingSlashFromPath(): void
    {
        $resolver = new S3UrlResolver(
            endpoint: 'https://s3.us-central-1.wasabisys.com',
            bucket: 'my-bucket',
            publicUrl: 'https://assets.efact.app/',
        );

        $this->assertSame(
            'https://assets.efact.app/tenant/images/logo.png',
            $resolver->resolve('/tenant/images/logo.png'),
        );
    }
}
