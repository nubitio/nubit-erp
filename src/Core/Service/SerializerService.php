<?php

namespace App\Core\Service;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializerService
{
    protected Serializer $serializer;

    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $propertyInfoExtractor = new PropertyInfoExtractor(
            [],
            [
                new PhpDocExtractor(),
                new ReflectionExtractor(),
            ]
        );
        $normalizers = [
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                $classMetadataFactory,
                $metadataAwareNameConverter,
                null,
                $propertyInfoExtractor,
            ),
        ];
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function serialize(mixed $data, string $format, array $context = []): string
    {
        return $this->serializer->serialize($data, $format, $context);
    }

    public function deserialize(mixed $data, string $type, string $format): mixed
    {
        return $this->serializer->deserialize(
            $data,
            $type,
            $format,
            [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true]
        );
    }
}
