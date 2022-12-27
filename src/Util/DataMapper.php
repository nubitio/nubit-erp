<?php

namespace App\Util;

class DataMapper
{
    /**
     * @template T
     * @param T $input
     * @param T $output
     * @return T
     */
    public static function map($input, $output)
    {
        foreach ($input as $key => $value) {
            if (!$value) {
                continue;
            }

            if (property_exists($output, $key)) {
                $output->$key = $value;
            }
        }

        return $output;
    }

    /**
     * @template T
     * @param T $input
     * @param string $class
     * @return T
     */
    public static function mapTo($input, string $class)
    {
        $output = new $class();
        return self::map($input, $output);
    }
}