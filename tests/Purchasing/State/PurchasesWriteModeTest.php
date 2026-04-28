<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use App\Purchasing\State\PurchasesWriteMode;
use PHPUnit\Framework\TestCase;

final class PurchasesWriteModeTest extends TestCase
{
    public function testKnownModeConstants(): void
    {
        self::assertSame('legacy', PurchasesWriteMode::LEGACY);
        self::assertSame('dual_write', PurchasesWriteMode::DUAL_WRITE);
        self::assertSame('v2_authoritative', PurchasesWriteMode::AUTHORITATIVE);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('knownModesProvider')]
    public function testNormalizeReturnsKnownModeUnchanged(string $input, string $expected): void
    {
        self::assertSame($expected, PurchasesWriteMode::normalize($input));
    }

    /** @return array<string, array{string, string}> */
    public static function knownModesProvider(): array
    {
        return [
            'legacy lowercase'       => ['legacy', PurchasesWriteMode::LEGACY],
            'dual_write lowercase'   => ['dual_write', PurchasesWriteMode::DUAL_WRITE],
            'authoritative'          => ['v2_authoritative', PurchasesWriteMode::AUTHORITATIVE],
            'legacy uppercase'       => ['LEGACY', PurchasesWriteMode::LEGACY],
            'dual_write uppercase'   => ['DUAL_WRITE', PurchasesWriteMode::DUAL_WRITE],
            'authoritative mixed'    => ['V2_Authoritative', PurchasesWriteMode::AUTHORITATIVE],
            'with leading spaces'    => ['  legacy  ', PurchasesWriteMode::LEGACY],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownModesProvider')]
    public function testNormalizeDefaultsToDualWriteForUnknownValues(string $input): void
    {
        self::assertSame(PurchasesWriteMode::DUAL_WRITE, PurchasesWriteMode::normalize($input));
    }

    /** @return array<string, array{string}> */
    public static function unknownModesProvider(): array
    {
        return [
            'empty string'   => [''],
            'garbage value'  => ['garbage'],
            'partial match'  => ['dual'],
            'numeric string' => ['123'],
        ];
    }
}
