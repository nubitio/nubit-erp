<?php

namespace App\Core\Domain\Util;

use App\Core\Domain\Annotation\Ignore;
use Doctrine\Common\Collections\Collection;

class DataMapper
{
    /**
     * @template T of object
     *
     * @param mixed $input
     * @param T|class-string<T> $output
     * @param array<string> $ignoredFields
     *
     * @return T
     * @phpstan-return T
     */
    public static function map(mixed $input, mixed $output, array $ignoredFields = []): object
    {
        if (is_string($output)) {
            $output = new $output();
        }

        foreach (get_object_vars((object)$input) as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (in_array($key, $ignoredFields, true)) {
                continue;
            }

            try {
                $reflectionProperty = new \ReflectionProperty(get_class($output), strval($key));
                $ignore = $reflectionProperty->getAttributes(Ignore::class)[0] ?? null;
                if (null !== $ignore) {
                    continue;
                }
            } catch (\ReflectionException $e) {
                continue;
            }

            if (property_exists($output, strval($key))) {
                if (is_object($value) && !($value instanceof \DateTimeInterface)) {
                    if ($value instanceof Collection) {
                        $output->$key = [];
                        /** @var object $item */
                        foreach ($value as $item) {
                            $type = ReflectionUtil::getPropertyType($output, strval($key));
                            if ($type === null) {
                                throw new \RuntimeException('Type not found');
                            }
                            $output->$key[] = self::map($item, $type);
                        }
                    } else {
                        $type = ReflectionUtil::getPropertyType($output, strval($key));
                        if ($type === null) {
                            throw new \RuntimeException('Type not found');
                        }
                        $output->$key = self::map($value, $type);
                    }
                } else {
                    if (is_array($value)) {
                        $type = ReflectionUtil::getPropertyType($output, strval($key));
                        if ($type === null) {
                            throw new \RuntimeException('Type not found');
                        }
                        $output->$key = [];
                        foreach ($value as $item) {
                            $output->$key[] = self::map($item, $type);
                        }
                    } else {
                        $output->$key = $value;
                    }
                }
            }
        }

        return $output;
    }
}
