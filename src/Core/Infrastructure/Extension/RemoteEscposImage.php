<?php

namespace App\Core\Infrastructure\Extension;

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\NativeEscposImage;

class RemoteEscposImage extends EscposImage
{
    public static function loadImage(string $filename): EscposImage
    {
        return new NativeEscposImage($filename);
    }
}