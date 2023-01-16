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
                $output->$key = $value;
            }
        }

        return $output;
    }
}
