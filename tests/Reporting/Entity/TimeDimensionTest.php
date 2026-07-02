<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Entity;

use App\Reporting\Entity\TimeDimension;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimeDimensionTest extends TestCase
{
    public function testDefaultsAreNull(): void
    {
        $td = new TimeDimension();

        // $id is a non-nullable int — uninitialized until setId() is called
        self::assertNull($td->getDate());
        self::assertNull($td->getYear());
        self::assertNull($td->getMonth());
        self::assertNull($td->getDay());
        self::assertNull($td->getQuarter());
        self::assertNull($td->getWeek());
        self::assertNull($td->getDayName());
        self::assertNull($td->getMonthName());
        self::assertNull($td->isHoliday());
        self::assertNull($td->isWeekend());
        self::assertNull($td->getEvent());
    }

    public function testSettersReturnFluentInterface(): void
    {
        $td = new TimeDimension();
        $date = new DateTimeImmutable('2024-03-15');

        $result = $td
            ->setId(20240315)
            ->setDate($date)
            ->setYear(2024)
            ->setMonth(3)
            ->setDay(15)
            ->setQuarter(1)
            ->setWeek(11)
            ->setDayName('viernes')
            ->setMonthName('marzo')
            ->setHoliday(false)
            ->setWeekend(false)
            ->setEvent('Día del trabajo');

        self::assertSame($td, $result);
    }

    public function testGettersReturnSetValues(): void
    {
        $td = new TimeDimension();
        $date = new DateTimeImmutable('2024-07-28');

        $td
            ->setId(20240728)
            ->setDate($date)
            ->setYear(2024)
            ->setMonth(7)
            ->setDay(28)
            ->setQuarter(3)
            ->setWeek(30)
            ->setDayName('domingo')
            ->setMonthName('julio')
            ->setHoliday(true)
            ->setWeekend(true)
            ->setEvent('Fiestas Patrias');

        self::assertSame(20240728, $td->getId());
        self::assertSame($date, $td->getDate());
        self::assertSame(2024, $td->getYear());
        self::assertSame(7, $td->getMonth());
        self::assertSame(28, $td->getDay());
        self::assertSame(3, $td->getQuarter());
        self::assertSame(30, $td->getWeek());
        self::assertSame('domingo', $td->getDayName());
        self::assertSame('julio', $td->getMonthName());
        self::assertTrue($td->isHoliday());
        self::assertTrue($td->isWeekend());
        self::assertSame('Fiestas Patrias', $td->getEvent());
    }

    public function testNullableFieldsAcceptNull(): void
    {
        $td = new TimeDimension();
        $td
            ->setId(20240101)
            ->setDate(null)
            ->setYear(null)
            ->setMonth(null)
            ->setDay(null)
            ->setQuarter(null)
            ->setWeek(null)
            ->setDayName(null)
            ->setMonthName(null)
            ->setHoliday(null)
            ->setWeekend(null)
            ->setEvent(null);

        self::assertNull($td->getDate());
        self::assertNull($td->getYear());
        self::assertNull($td->getMonth());
        self::assertNull($td->getDay());
        self::assertNull($td->getQuarter());
        self::assertNull($td->getWeek());
        self::assertNull($td->getDayName());
        self::assertNull($td->getMonthName());
        self::assertNull($td->isHoliday());
        self::assertNull($td->isWeekend());
        self::assertNull($td->getEvent());
    }
}
