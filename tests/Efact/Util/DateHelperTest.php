<?php

declare(strict_types=1);

namespace App\Tests\Efact\Util;

use App\Efact\Util\DateHelper;
use PHPUnit\Framework\TestCase;

final class DateHelperTest extends TestCase
{
    public function testNowReturnsUtc(): void
    {
        $dt = DateHelper::now();
        self::assertSame('UTC', $dt->getTimezone()->getName());
    }

    public function testNowLimaReturnsLima(): void
    {
        $dt = DateHelper::nowLima();
        self::assertSame('America/Lima', $dt->getTimezone()->getName());
    }

    public function testTodayIsMidnightUtc(): void
    {
        $dt = DateHelper::today();
        self::assertSame('00:00:00', $dt->format('H:i:s'));
        self::assertSame('UTC', $dt->getTimezone()->getName());
    }

    public function testParseReturnsImmutable(): void
    {
        $dt = DateHelper::parse('2024-06-15');
        self::assertInstanceOf(\DateTimeImmutable::class, $dt);
        self::assertSame('2024-06-15', $dt->format('Y-m-d'));
    }

    public function testFormatDefaultsToYmdHis(): void
    {
        $dt = new \DateTimeImmutable('2024-01-05 09:07:03', new \DateTimeZone('UTC'));
        self::assertSame('2024-01-05 09:07:03', DateHelper::format($dt));
    }

    public function testFormatWithCustomPattern(): void
    {
        $dt = new \DateTimeImmutable('2024-03-05', new \DateTimeZone('UTC'));
        self::assertSame('2024-03-05', DateHelper::format($dt, 'Y-m-d'));
    }

    public function testDiffInDays(): void
    {
        $from = new \DateTimeImmutable('2024-01-01');
        $to = new \DateTimeImmutable('2024-01-11');
        self::assertSame(10, DateHelper::diffInDays($from, $to));
    }

    public function testDiffInHours(): void
    {
        $from = new \DateTimeImmutable('2024-01-01 00:00:00');
        $to = new \DateTimeImmutable('2024-01-01 03:00:00');
        self::assertSame(3, DateHelper::diffInHours($from, $to));
    }

    public function testAddDaysReturnsNewDate(): void
    {
        $dt = new \DateTimeImmutable('2024-01-10');
        $result = DateHelper::addDays($dt, 5);
        self::assertSame('2024-01-15', $result->format('Y-m-d'));
    }

    public function testAddHoursReturnsNewDate(): void
    {
        $dt = new \DateTimeImmutable('2024-01-10 10:00:00');
        $result = DateHelper::addHours($dt, 3);
        self::assertSame('2024-01-10 13:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testIsWeekendSaturday(): void
    {
        $sat = new \DateTimeImmutable('2024-01-06'); // Saturday
        self::assertTrue(DateHelper::isWeekend($sat));
    }

    public function testIsWeekdayMonday(): void
    {
        $mon = new \DateTimeImmutable('2024-01-08'); // Monday
        self::assertTrue(DateHelper::isWeekday($mon));
        self::assertFalse(DateHelper::isWeekend($mon));
    }

    public function testStartOfMonth(): void
    {
        $dt = new \DateTimeImmutable('2024-03-15 14:30:00');
        $start = DateHelper::startOfMonth($dt);
        self::assertSame('2024-03-01 00:00:00', $start->format('Y-m-d H:i:s'));
    }

    public function testEndOfMonth(): void
    {
        $dt = new \DateTimeImmutable('2024-03-15');
        $end = DateHelper::endOfMonth($dt);
        self::assertSame('2024-03-31', $end->format('Y-m-d'));
    }
}
