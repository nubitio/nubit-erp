<?php

namespace App\Core\Domain\Util;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

class ReflectionUtil
{
    /**
     * @return class-string|null
     */
    public static function getPropertyType(object $object, string $property): ?string
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        $propertyInfoExtractor = new PropertyInfoExtractor([], [$phpDocExtractor, $reflectionExtractor]);

        /** @var Type[] $types */
        $types = $propertyInfoExtractor->getTypes(get_class($object), $property);

        if (count($types) > 0) {
            if ($types[0]->isCollection()) {
                $className = $types[0]->getCollectionValueTypes()[0]->getClassName();
                if (null !== $className && class_exists($className)) {
                    return $className;
                } else {
                    return null;
                }
            } else {
                $className = $types[0]->getClassName();
                if (null !== $className && class_exists($className)) {
                    return $className;
                } else {
                    return null;
                }
            }
        }

        return null;
    }

    public static function hasProperty(object|string $obj, string $field): bool
    {
        if (is_string($obj)) {
            $obj = new $obj();
        }

        $parent = get_parent_class($obj);

        if ($parent) {
            $parentObj = new $parent();
            $parentAttributes = get_object_vars($parentObj);
            $childAttributes = get_object_vars($obj);

            $attributes = array_diff_key($childAttributes, $parentAttributes);

            return array_key_exists($field, $attributes);
        }

        return property_exists($obj, $field);
    }
}
