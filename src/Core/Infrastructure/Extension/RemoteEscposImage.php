<?php

namespace App\Core\Infrastructure\Extension;

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\ImagickEscposImage;

class RemoteEscposImage extends EscposImage
{
    /**
     * Load an image from a remote URL.
     *
     * @throws \Exception
     */
    public static function loadImage(string $imageUrl): ImagickEscposImage
    {
        $imageContent = @file_get_contents($imageUrl);
        if (false === $imageContent) {
            throw new \Exception("Failed to download image from URL: $imageUrl");
        }

        $tempPath = sys_get_temp_dir().'/escpos_temp_image.png';
        if (false === file_put_contents($tempPath, $imageContent)) {
            throw new \Exception('Failed to save downloaded image to temporary file.');
        }

        return new ImagickEscposImage($tempPath);
    }
}
