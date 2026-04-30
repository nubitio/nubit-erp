<?php

declare(strict_types=1);

namespace App\Tests\Efact\Util;

use App\Efact\Util\ArrayHelper;
use PHPUnit\Framework\TestCase;

final class ArrayHelperTest extends TestCase
{
    // ── get ──────────────────────────────────────────────────────────────────

    public function testGetReturnsTopLevelValue(): void
    {
        self::assertSame('bar', ArrayHelper::get(['foo' => 'bar'], 'foo'));
    }

    public function testGetReturnsDotNotationValue(): void
    {
        self::assertSame('baz', ArrayHelper::get(['a' => ['b' => 'baz']], 'a.b'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        self::assertSame('default', ArrayHelper::get([], 'missing', 'default'));
    }

    public function testGetReturnsNullDefaultWhenNotProvided(): void
    {
        self::assertNull(ArrayHelper::get([], 'x'));
    }

    public function testGetReturnsFalseForMissingNestedKey(): void
    {
        self::assertNull(ArrayHelper::get(['a' => []], 'a.b.c'));
    }

    // ── set ──────────────────────────────────────────────────────────────────

    public function testSetTopLevelKey(): void
    {
        $arr = [];
        ArrayHelper::set($arr, 'foo', 'bar');
        self::assertSame('bar', $arr['foo']);
    }

    public function testSetNestedKey(): void
    {
        $arr = [];
        ArrayHelper::set($arr, 'a.b.c', 99);
        self::assertSame(99, $arr['a']['b']['c']);
    }

    public function testSetOverridesExisting(): void
    {
        $arr = ['x' => 1];
        ArrayHelper::set($arr, 'x', 2);
        self::assertSame(2, $arr['x']);
    }

    // ── only / except ─────────────────────────────────────────────────────────

    public function testOnlyKeepsRequestedKeys(): void
    {
        $result = ArrayHelper::only(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']);
        self::assertSame(['a' => 1, 'c' => 3], $result);
    }

    public function testExceptRemovesRequestedKeys(): void
    {
        $result = ArrayHelper::except(['a' => 1, 'b' => 2, 'c' => 3], ['b']);
        self::assertSame(['a' => 1, 'c' => 3], $result);
    }

    // ── flatten ──────────────────────────────────────────────────────────────

    public function testFlattenProducessDotKeys(): void
    {
        $result = ArrayHelper::flatten(['a' => ['b' => 1, 'c' => 2], 'd' => 3]);
        self::assertSame(['a.b' => 1, 'a.c' => 2, 'd' => 3], $result);
    }

    public function testFlattenWithCustomSeparator(): void
    {
        $result = ArrayHelper::flatten(['x' => ['y' => 'z']], '/');
        self::assertSame(['x/y' => 'z'], $result);
    }

    // ── isAssociative ─────────────────────────────────────────────────────────

    public function testIsAssociativeReturnsTrueForStringKeys(): void
    {
        self::assertTrue(ArrayHelper::isAssociative(['a' => 1, 'b' => 2]));
    }

    public function testIsAssociativeReturnsFalseForIntKeys(): void
    {
        self::assertFalse(ArrayHelper::isAssociative([1, 2, 3]));
    }

    public function testIsAssociativeReturnsFalseForEmptyArray(): void
    {
        self::assertFalse(ArrayHelper::isAssociative([]));
    }
}
