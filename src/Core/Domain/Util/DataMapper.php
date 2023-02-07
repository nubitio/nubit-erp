<?php

namespace App\Core\Domain\Util;

use App\Core\Domain\Annotation\Ignore;
use Doctrine\Common\Collections\Collection;

class DataMapper
{
    /**
     * @template T
     *
     * @param T $input
     * @param T|string $output
     *
     * @return T
     */
    public static function map($input, mixed $output)
    {
        if (is_string($output)) {
            $output = new $output();
        }

        foreach ($input as $key => $value) {
            if ($value === null) {
                continue;
            }

            try {
                $reflectionProperty = new \ReflectionProperty(get_class($output), $key);
                $ignore = $reflectionProperty->getAttributes(Ignore::class)[0] ?? null;
                if (null !== $ignore) {
                    continue;
                }
            } catch (\ReflectionException $e) {
                continue;
            }

            if (property_exists($output, $key)) {
                if (is_object($value) && !($value instanceof \DateTimeInterface)) {
                    if ($value instanceof Collection) {
                        $output->$key = [];
                        foreach ($value as $item) {
                            $type = ReflectionUtil::getPropertyType($output, $key);
                            $output->$key[] = self::map($item, $type);
                        }
                    } else {
                        $type = ReflectionUtil::getPropertyType($output, $key);
                        $output->$key = self::map($value, $type);
                    }
                } else {
                    if (is_array($value)) {
                        $type = ReflectionUtil::getPropertyType($output, $key);
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
