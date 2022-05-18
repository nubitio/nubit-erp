<?php

namespace App\Domain\Service;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializerService
{
    protected Serializer $serializer;

    public function __construct()
    {
        $normalizers = [new ObjectNormalizer()];
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function serialize(mixed $data, string $format): string
    {
        return $this->serializer->serialize($data, $format);
    }

    public function deserialize(mixed $data, string $type, string $format): object
    {
        return $this->serializer->deserialize($data, $type, $format);
    }
}