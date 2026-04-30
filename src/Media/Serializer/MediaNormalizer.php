<?php

declare(strict_types=1);

namespace App\Media\Serializer;

use Override;
use ArrayObject;
use Nubit\Platform\Tenant\Context\TenantContext;
use App\Efact\Filesystem\S3UrlResolver;
use App\Efact\Config\EfactConstants as Constants;
use App\Media\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class MediaNormalizer implements NormalizerInterface
{
    private const string ALREADY_CALLED = 'MEDIA_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private S3UrlResolver $s3UrlResolver,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param Media $data
     *
     * @return array<string, mixed>|ArrayObject<string, mixed>|bool|float|int|string|null
     *
     * @throws ExceptionInterface
     */
    #[Override]
    public function normalize(
        $data,
        ?string $format = null,
        array $context = [],
    ): array | ArrayObject | bool | float | int | string | null {
        $context[self::ALREADY_CALLED] = true;

        $path = $this->tenantContext->getTenantName() . '/' . Constants::IMAGE_DIR . '/' . $data->getPath();
        $data->setPath($this->s3UrlResolver->resolve($path));

        return $this->normalizer->normalize($data, $format, $context);
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function supportsNormalization(
        $data,
        ?string $format = null,
        array $context = []
    ): bool {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Media;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Media::class => true];
    }
}
