<?php

namespace App\Util;

class ColorUtils
{
    public static function contrastColor(string $hexColor): string
    {
        $hexColor = str_replace('#', '', $hexColor);
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? '#000000' : '#FFFFFF';
    }

    public static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) {
            $h = $s = 0;
        } else {
            $s = $d / (1 - abs(2 * $l - 1));
            switch ($max) {
                case $r:
                    $h = 60 * fmod((($g - $b) / $d), 6);
                    if ($b > $g) {
                        $h += 360;
                    }
                    break;
                case $g:
                    $h = 60 * (($b - $r) / $d + 2);
                    break;
                case $b:
                    $h = 60 * (($r - $g) / $d + 4);
                    break;
            }
        }
        return [$h, $s, $l];
    }

    public static function hslToRgb(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod(($h / 60), 2) - 1));
        $m = $l - ($c / 2);
        if ($h < 60) {
            $r = $c;
            $g = $x;
            $b = 0;
        } elseif ($h < 120) {
            $r = $x;
            $g = $c;
            $b = 0;
        } elseif ($h < 180) {
            $r = 0;
            $g = $c;
            $b = $x;
        } elseif ($h < 240) {
            $r = 0;
            $g = $x;
            $b = $c;
        } elseif ($h < 300) {
            $r = $x;
            $g = 0;
            $b = $c;
        } else {
            $r = $c;
            $g = 0;
            $b = $x;
        }
        $r = ($r + $m) * 255;
        $g = ($g + $m) * 255;
        $b = ($b + $m) * 255;
        return [$r, $g, $b];
    }

    public static function lightenColor(string $hexColor, int $percent): string
    {
        $hexColor = str_replace('#', '', $hexColor);
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
        $hsl = self::rgbToHsl($r, $g, $b);
        $hsl[2] += $percent / 100;
        $hsl[2] = min($hsl[2], 1);
        $rgb = self::hslToRgb($hsl[0], $hsl[1], $hsl[2]);
        $r = dechex($rgb[0]);
        $g = dechex($rgb[1]);
        $b = dechex($rgb[2]);
        return '#' . $r . $g . $b;
    }
}