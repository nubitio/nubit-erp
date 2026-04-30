<?php

declare(strict_types=1);

namespace App\Tests\Media\Serializer;

use App\Efact\Filesystem\S3UrlResolver;
use App\Media\Entity\Media;
use App\Media\Serializer\MediaNormalizer;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MediaNormalizerTest extends TestCase
{
    private function makeNormalizer(): MediaNormalizer
    {
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(
            static fn (mixed $data): array => ['path' => ($data instanceof Media) ? $data->getPath() : '']
        );

        // S3UrlResolver is final — instantiate directly with CDN public URL
        $s3 = new S3UrlResolver(
            endpoint: 'https://s3.wasabisys.com',
            bucket: 'efact-bucket',
            publicUrl: 'https://cdn.example.com',
        );

        $tenantCtx = new TenantContext();
        $tenantCtx->setTenant(tenantId: 1, tenantName: 'tenant1', tenantDomain: 'tenant1.efact.pe', requestId: null);

        return new MediaNormalizer($inner, $s3, $tenantCtx);
    }

    // ── supportsNormalization ─────────────────────────────────────────────────

    public function testSupportsMediaInstance(): void
    {
        $normalizer = $this->makeNormalizer();
        self::assertTrue($normalizer->supportsNormalization(new Media()));
    }

    public function testDoesNotSupportNonMediaObject(): void
    {
        $normalizer = $this->makeNormalizer();
        self::assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    public function testDoesNotSupportWhenAlreadyCalledFlagIsSet(): void
    {
        $normalizer = $this->makeNormalizer();
        $context = ['MEDIA_NORMALIZER_ALREADY_CALLED' => true];
        self::assertFalse($normalizer->supportsNormalization(new Media(), null, $context));
    }

    // ── getSupportedTypes ─────────────────────────────────────────────────────

    public function testGetSupportedTypesReturnsMediClass(): void
    {
        $types = $this->makeNormalizer()->getSupportedTypes(null);
        self::assertArrayHasKey(Media::class, $types);
        self::assertTrue($types[Media::class]);
    }

    // ── normalize ─────────────────────────────────────────────────────────────

    public function testNormalizeResolvesS3Url(): void
    {
        $normalizer = $this->makeNormalizer();

        $media = new Media();
        $media->setPath('logo.png');

        $result = $normalizer->normalize($media);

        // path = tenantName/images/filename → CDN prefix
        self::assertSame('https://cdn.example.com/tenant1/images/logo.png', $result['path']);
    }

    public function testNormalizeSetsAlreadyCalledFlagOnContext(): void
    {
        $capturedContext = [];

        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(
            static function (mixed $data, ?string $format, array $context) use (&$capturedContext): array {
                $capturedContext = $context;
                return [];
            }
        );

        $s3 = new S3UrlResolver(
            endpoint: 'https://s3.wasabisys.com',
            bucket: 'efact-bucket',
            publicUrl: 'https://cdn.example.com',
        );

        $tenantCtx = new TenantContext();
        $tenantCtx->setTenant(1, 'tenant1', 'x.pe', null);

        $normalizer = new MediaNormalizer($inner, $s3, $tenantCtx);
        $media = new Media();
        $media->setPath('img.jpg');
        $normalizer->normalize($media);

        self::assertTrue($capturedContext['MEDIA_NORMALIZER_ALREADY_CALLED']);
    }
}
