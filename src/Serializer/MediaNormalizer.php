<?php

namespace App\Serializer;

use App\Core\Domain\Util\Constants;
use App\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MediaNormalizer implements NormalizerInterface
{
    private const string ALREADY_CALLED = 'MEDIA_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param Media $data
     *
     * @throws ExceptionInterface
     */
    public function normalize(
        $data,
        ?string $format = null,
        array $context = [],
    ): array | \ArrayObject | bool | float | int | string | null {
        $context[self::ALREADY_CALLED] = true;

        $baseUrl = $_ENV['S3_ENDPOINT'] . '/' . $_ENV['S3_BUCKET_NAME'] . '/';

        $folder = Constants::IMAGE_UPLOAD_DIR;

        $data->setPath($baseUrl . $folder . $data->getPath());

        return $this->normalizer->normalize($data, $format, $context);
    }

    /**
     * @inheritdoc
     */
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
    public function getSupportedTypes(?string $format): array
    {
        return [Media::class => true];
    }
}
