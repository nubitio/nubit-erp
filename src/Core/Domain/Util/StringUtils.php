<?php

namespace App\Core\Domain\Util;

class StringUtils
{
    public static function toCamelCase(string $str): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
        $str[0] = strtolower($str[0]);

        return $str;
    }

    public static function toKebabCase(string $str): string
    {
        return strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '-$0', $str)));
    }

    public static function toPascalCase(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}
