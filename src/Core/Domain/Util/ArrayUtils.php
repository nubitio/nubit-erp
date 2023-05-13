<?php

namespace App\Core\Domain\Util;

class ArrayUtils
{
    /**
     * @param array<int, mixed> $array
     *
     * @return mixed|null
     */
    public static function find(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }
}
