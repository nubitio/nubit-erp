<?php

namespace App\Core\Domain\Util;

use Doctrine\Common\Collections\ArrayCollection;
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

            if (property_exists($output, $key)) {
                if (is_object($value) && !($value instanceof \DateTimeInterface)) {
                    if ($value instanceof Collection) {
                        $output->$key = new ArrayCollection();
                        foreach ($value as $item) {
                            $type = ReflectionUtil::getPropertyType($output, $key);
                            $output->$key->add(self::map($item, $type));
                        }
                    } else {
                        $type = ReflectionUtil::getPropertyType($output, $key);
                        $output->$key = self::map($value, $type);
                    }
                } else {
                    $output->$key = $value;
                }
            }
        }

        return $output;
    }
}
