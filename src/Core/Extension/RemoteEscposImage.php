<?php

declare(strict_types=1);

namespace App\Core\Extension;

use Imagick;
use ImagickPixel;
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
            return null;
        }

        $tempPath = sys_get_temp_dir() . '/escpos_temp_image_' . uniqid() . '.png';
        if (false === file_put_contents($tempPath, $imageContent)) {
            return null;
        }

        // Try Imagick first if available
        if (class_exists(Imagick::class)) {
            try {
                $processedPath = self::preprocessWithImagick($tempPath);
                return new ImagickEscposImage($processedPath);
            } catch (Throwable) {
                // Fall through to GD
            }
        }

        // Fallback to GD if available
        if (extension_loaded('gd')) {
            try {
                $processedPath = self::preprocessWithGd($tempPath);
                return GdEscposImage::load($processedPath);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Preprocess image with Imagick for better thermal printing.
     * Darkens light colors so they appear on thermal printers.
     */
    private static function preprocessWithImagick(string $imagePath): string
    {
        $image = new Imagick($imagePath);

        // Flatten image to remove transparency (white background)
        $image->setImageBackgroundColor(new ImagickPixel('white'));
        $image = $image->flattenImages();

        // Convert to grayscale
        $image->setImageColorspace(Imagick::COLORSPACE_GRAY);

        // Darken midtones using level adjustment
        // This maps input range [0, 0.85] to output [0, 1], making light grays darker
        $image->levelImage(0, 0.5, 0.85 * Imagick::getQuantum());

        // Save processed image (let EscposImage do the final binarization)
        $processedPath = sys_get_temp_dir() . '/escpos_processed_' . uniqid() . '.png';
        $image->writeImage($processedPath);
        $image->destroy();

        return $processedPath;
    }

    /**
     * Preprocess image with GD for better thermal printing.
     */
    private static function preprocessWithGd(string $imagePath): string
    {
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            return $imagePath;
        }

        $src = match ($imageInfo[2]) {
            IMAGETYPE_PNG => imagecreatefrompng($imagePath),
            IMAGETYPE_JPEG => imagecreatefromjpeg($imagePath),
            IMAGETYPE_GIF => imagecreatefromgif($imagePath),
            default => null,
        };

        if ($src === null || $src === false) {
            return $imagePath;
        }

        $width = imagesx($src);
        $height = imagesy($src);

        // Create a white background image
        $dest = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($dest, 255, 255, 255);
        $black = imagecolorallocate($dest, 0, 0, 0);
        imagefill($dest, 0, 0, $white);

        // Copy with transparency handling
        imagecopy($dest, $src, 0, 0, 0, 0, $width, $height);

        // Convert to grayscale and apply threshold
        imagefilter($dest, IMG_FILTER_GRAYSCALE);
        imagefilter($dest, IMG_FILTER_CONTRAST, -30);

        // Manual threshold: pixels darker than 60% gray become black
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($dest, $x, $y);
                $gray = ($rgb >> 16) & 0xFF;
                if ($gray < 153) { // 60% of 255
                    imagesetpixel($dest, $x, $y, $black);
                } else {
                    imagesetpixel($dest, $x, $y, $white);
                }
            }
        }

        $processedPath = sys_get_temp_dir() . '/escpos_processed_' . uniqid() . '.png';
        imagepng($dest, $processedPath);
        imagedestroy($src);
        imagedestroy($dest);

        return $processedPath;
    }
}
