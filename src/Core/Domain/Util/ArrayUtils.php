<?php

namespace App\Core\Domain\Util;

class ArrayUtils
{
    /**
     * @param array $array
     * @param callable $callback
     * @return mixed|null
     */
    static public function find(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }
}