<?php

declare(strict_types=1);

namespace App\Tests\Sales\Domain;

use App\Sales\Domain\AuthorizedPrice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorizedPriceTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function cases(): iterable
    {
        yield 'same price, different scale' => ['10.40', '10.4000', true];
        yield 'till rounds a multiplier price to two decimals' => ['10.75', '10.7463', true];
        yield 'rounds half up' => ['10.41', '10.4050', true];
        yield 'exact four-decimal override' => ['10.4050', '10.4050', true];
        yield 'price from an older tariff (the stale-catalogue case)' => ['9.80', '10.10', false];
        yield 'one cent off' => ['10.39', '10.40', false];
        yield 'truncated instead of rounded' => ['10.74', '10.7463', false];
    }

    #[DataProvider('cases')]
    public function testMatches(string $submitted, string $expected, bool $authorized): void
    {
        self::assertSame($authorized, AuthorizedPrice::matches($submitted, $expected));
    }
}
