<?php

namespace App\Core\Domain\Util;

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

            if (property_exists($output, $key)) {
                if (is_iterable($value)) {
                    $type = ReflectionUtil::getPropertyType($output, $key);
                    $output->$key = array_map(fn($item) => self::map($item, $type), $value);
                } else if (is_object($value) && !($value instanceof \DateTimeInterface)) {
                    $type = ReflectionUtil::getPropertyType($output, $key);
                    $output->$key = self::map($value, $type);
                } else {
                    $output->$key = $value;
                }
            }
        }

        return $output;
    }
}
