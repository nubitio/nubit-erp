<?php

declare(strict_types=1);

namespace App\Tests\Efact\Util;

use App\Efact\Util\ColorUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorUtilsTest extends TestCase
{
    // ── contrastColor ─────────────────────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function contrastProvider(): array
    {
        return [
            'white background → black text'  => ['#FFFFFF', '#000000'],
            'black background → white text'  => ['#000000', '#FFFFFF'],
            'light yellow → black text'      => ['#FFFF00', '#000000'],
            'dark blue → white text'         => ['#003366', '#FFFFFF'],
            'without hash'                   => ['FFFFFF', '#000000'],
        ];
    }

    #[DataProvider('contrastProvider')]
    public function testContrastColor(string $input, string $expected): void
    {
        self::assertSame($expected, ColorUtils::contrastColor($input));
    }

    // ── hexToRgb ──────────────────────────────────────────────────────────────

    public function testHexToRgbWhite(): void
    {
        self::assertSame([255, 255, 255], ColorUtils::hexToRgb('#FFFFFF'));
    }

    public function testHexToRgbBlack(): void
    {
        self::assertSame([0, 0, 0], ColorUtils::hexToRgb('#000000'));
    }

    public function testHexToRgbRed(): void
    {
        self::assertSame([255, 0, 0], ColorUtils::hexToRgb('#FF0000'));
    }

    public function testHexToRgbWithoutHash(): void
    {
        self::assertSame([255, 0, 0], ColorUtils::hexToRgb('FF0000'));
    }

    // ── rgbToHex ──────────────────────────────────────────────────────────────

    public function testRgbToHexWhite(): void
    {
        self::assertSame('#ffffff', ColorUtils::rgbToHex(255, 255, 255));
    }

    public function testRgbToHexBlack(): void
    {
        self::assertSame('#000000', ColorUtils::rgbToHex(0, 0, 0));
    }

    public function testRgbToHexRed(): void
    {
        self::assertSame('#ff0000', ColorUtils::rgbToHex(255, 0, 0));
    }

    public function testRgbToHexRoundTrip(): void
    {
        $rgb = ColorUtils::hexToRgb('#3A6FBC');
        $hex = ColorUtils::rgbToHex($rgb[0], $rgb[1], $rgb[2]);
        self::assertSame('#3a6fbc', $hex);
    }

    // ── lightenColor / darkenColor ────────────────────────────────────────────

    public function testLightenColorMakesColorLighter(): void
    {
        $original = '#336699';
        $lightened = ColorUtils::lightenColor($original, 20);

        // Lightened color should be different from original
        self::assertNotSame(strtolower($original), strtolower($lightened));
        // And starts with #
        self::assertStringStartsWith('#', $lightened);
        self::assertSame(7, strlen($lightened));
    }

    public function testDarkenColorMakesColorDarker(): void
    {
        $original = '#99CCFF';
        $darkened = ColorUtils::darkenColor($original, 20);

        self::assertNotSame(strtolower($original), strtolower($darkened));
        self::assertStringStartsWith('#', $darkened);
    }

    public function testLightenWhiteStaysWhite(): void
    {
        // Lightening white can't go higher than white
        $result = ColorUtils::lightenColor('#FFFFFF', 50);
        self::assertStringStartsWith('#', $result);
        self::assertSame(7, strlen($result));
    }

    // ── rgbToHsl / hslToRgb ───────────────────────────────────────────────────

    public function testRgbToHslGrey(): void
    {
        // Pure grey: H=0, S=0, L=0.5
        [$h, $s, $l] = ColorUtils::rgbToHsl(128, 128, 128);
        self::assertSame(0, $h);
        self::assertSame(0, $s);
        self::assertEqualsWithDelta(0.502, $l, 0.01);
    }

    public function testHslToRgbRoundTrip(): void
    {
        $r = 100;
        $g = 150;
        $b = 200;
        [$h, $s, $l] = ColorUtils::rgbToHsl($r, $g, $b);
        [$rOut, $gOut, $bOut] = ColorUtils::hslToRgb((float) $h, (float) $s, (float) $l);

        self::assertEqualsWithDelta($r, $rOut, 1.0);
        self::assertEqualsWithDelta($g, $gOut, 1.0);
        self::assertEqualsWithDelta($b, $bOut, 1.0);
    }
}
