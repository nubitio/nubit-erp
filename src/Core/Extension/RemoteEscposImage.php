<?php

namespace App\Core\Extension;

use Imagick;
use Throwable;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\ImagickEscposImage;

class RemoteEscposImage extends EscposImage
{
    /**
     * Load an image from a remote URL.
     * Prefer Imagick if available, otherwise fall back to GD.
     * If neither extension is available or the image is empty, return null.
     *
     * @throws Exception
     */
    public static function loadImage(string $imageUrl): ?EscposImage
    {
        if ($imageUrl === '') {
            return null;
        }

        $imageContent = @file_get_contents($imageUrl);
        if (false === $imageContent) {
            // Gracefully skip the logo if it cannot be downloaded
            return null;
        }

        $tempPath = sys_get_temp_dir() . '/escpos_temp_image_' . uniqid() . '.png';
        if (false === file_put_contents($tempPath, $imageContent)) {
            // Gracefully skip if cannot write a temp file
            return null;
        }

        // Try Imagick first if available
        if (class_exists(Imagick::class)) {
            try {
                return new ImagickEscposImage($tempPath);
            } catch (Throwable) {
                // Fall through to GD
            }
        }

        // Fallback to GD if available
        if (extension_loaded('gd')) {
            try {
                return GdEscposImage::load($tempPath);
            } catch (Throwable) {
                return null;
            }
        }

        // Neither Imagick nor GD available
        return null;
    }
}
