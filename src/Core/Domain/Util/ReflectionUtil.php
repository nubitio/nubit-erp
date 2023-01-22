<?php

namespace App\Core\Domain\Util;

class ReflectionUtil
{
    public static function getPropertyType(object $object, string $property): ?string
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);

        if ($property->hasType()) {
            return $property->getType()->getName();
        }

        return null;
    }
}