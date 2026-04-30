<?php

declare(strict_types=1);

namespace App\Tests\Efact\Util;

use App\Efact\Util\StringHelper;
use PHPUnit\Framework\TestCase;

final class StringHelperTest extends TestCase
{
    public function testStartsWith(): void
    {
        self::assertTrue(StringHelper::startsWith('hello world', 'hello'));
        self::assertFalse(StringHelper::startsWith('hello world', 'world'));
    }

    public function testEndsWith(): void
    {
        self::assertTrue(StringHelper::endsWith('hello world', 'world'));
        self::assertFalse(StringHelper::endsWith('hello world', 'hello'));
    }

    public function testContains(): void
    {
        self::assertTrue(StringHelper::contains('hello world', 'lo wo'));
        self::assertFalse(StringHelper::contains('hello world', 'xyz'));
    }

    public function testLimitTruncatesLongString(): void
    {
        $result = StringHelper::limit('hello world', 5);
        self::assertSame('hello...', $result);
    }

    public function testLimitKeepsShortString(): void
    {
        self::assertSame('hi', StringHelper::limit('hi', 10));
    }

    public function testLimitWithCustomEnd(): void
    {
        self::assertSame('hello~', StringHelper::limit('hello world', 5, '~'));
    }

    public function testRandomGeneratesCorrectLength(): void
    {
        $str = StringHelper::random(24);
        self::assertSame(24, strlen($str));
    }

    public function testRandomOnlyContainsAlphanumeric(): void
    {
        $str = StringHelper::random(64);
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $str);
    }

    public function testSlug(): void
    {
        self::assertSame('hello-world', StringHelper::slug('Hello World'));
        self::assertSame('hola-mundo', StringHelper::slug('Hola Mundo'));
    }
}
