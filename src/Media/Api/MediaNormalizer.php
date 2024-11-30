<?php

namespace App\Media\Api;

use App\Core\Domain\Util\Constants;
use App\Media\Domain\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MediaNormalizer implements NormalizerInterface
{
    private const string ALREADY_CALLED = 'MEDIA_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function normalize(
        $data,
        ?string $format = null,
        array $context = [],
    ): array|\ArrayObject|bool|float|int|string|null {
        $context[self::ALREADY_CALLED] = true;

        $baseUrl = $_ENV['S3_ENDPOINT'].'/'.$_ENV['S3_BUCKET_NAME'].'/';

        $folder = Constants::IMAGE_UPLOAD_DIR;

        $data->path = $baseUrl.$folder.$data->path;

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Media;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Media::class => true];
    }
}
