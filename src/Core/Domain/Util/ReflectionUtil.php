<?php

namespace App\Core\Domain\Util;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class ReflectionUtil
{
    public static function getPropertyType(object $object, string $property): ?string
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        $propertyInfoExtractor = new PropertyInfoExtractor([], [$phpDocExtractor, $reflectionExtractor]);

        /** @var Type[] $types */
        $types = $propertyInfoExtractor->getTypes(get_class($object), $property);

        if (count($types) > 0) {
            if ($types[0]->isCollection()) {
                return $types[0]->getCollectionValueTypes()[0]->getClassName();
            } else {
                return $types[0]->getClassName();
            }
        }

        return null;
    }
}