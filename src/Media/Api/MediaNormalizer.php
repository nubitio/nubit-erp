<?php

namespace App\Media\Api;

use App\Core\Domain\Util\Constants;
use App\Media\Domain\Media;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MediaNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MEDIA_NORMALIZER_ALREADY_CALLED';

    public function normalize($object, string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        $baseUrl = $_ENV['S3_ENDPOINT'] . '/' . $_ENV['S3_BUCKET_NAME'] . '/';

        $folder = Constants::IMAGE_UPLOAD_DIR;

        $object->path = $baseUrl . $folder . $object->path;

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Media;
    }
}