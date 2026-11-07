<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Sire;

use App\Reporting\Sire\SireTextFormat;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class SireTextFormatTest extends TestCase
{
    public function testMoneyAlwaysHasTwoDecimalsAndNeverNegativeZero(): void
    {
        self::assertSame('0.00', SireTextFormat::money(null));
        self::assertSame('0.00', SireTextFormat::money('0', true));
        self::assertSame('12.35', SireTextFormat::money('12.345'));
        self::assertSame('-12.35', SireTextFormat::money('12.345', true));
        self::assertSame('-5.00', SireTextFormat::signedMoney(BigDecimal::of('-5')));
    }

    public function testNumberDropsLeadingZerosOnlyForDigits(): void
    {
        self::assertSame('28806', SireTextFormat::number('00028806'));
        self::assertSame('0', SireTextFormat::number('000'));
        self::assertSame('A-001', SireTextFormat::number('A-001'));
        self::assertSame('', SireTextFormat::number(null));
    }

    public function testBookFileNameFollowsSunatConvention(): void
    {
        self::assertSame('LE2060123456720251200140400021112.TXT', SireTextFormat::bookFileName(
            '20601234567',
            '202512',
            '140400',
            '02',
            '1112',
            '.TXT',
        ));
    }

    public function testExchangeRateOnlyForForeignCurrency(): void
    {
        self::assertSame('', SireTextFormat::exchangeRate('PEN', '3.75'));
        self::assertSame('3.752', SireTextFormat::exchangeRate('USD', '3.7516'));
        self::assertSame('', SireTextFormat::exchangeRate('USD', '0'));
    }
}
