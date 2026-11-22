<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\Service\ProductChangeFeed;
use PHPUnit\Framework\TestCase;

final class ProductChangeFeedTest extends TestCase
{
    public function testTheWindowStartsBeforeTheCursorSoInFlightWritesAreNotLost(): void
    {
        $start = ProductChangeFeed::windowStart('2026-11-22 04:00:30');

        self::assertSame('2026-11-22 04:00:20', $start->format('Y-m-d H:i:s'));
    }

    public function testTheWindowCrossesMidnightAndMonthBoundaries(): void
    {
        self::assertSame(
            '2026-11-30 23:59:55',
            ProductChangeFeed::windowStart('2026-12-01 00:00:05')->format('Y-m-d H:i:s'),
        );
    }

    public function testOnlyTheServersOwnCursorFormatIsAccepted(): void
    {
        self::assertTrue(ProductChangeFeed::isValidCursor('2026-11-22 04:00:30'));

        foreach ([
            '',
            'ayer',
            '2026-11-22',
            '2026-11-22T04:00:30Z',
            '2026-13-40 99:99:99',
            '2026-11-22 04:00:30; DROP',
        ] as $bad) {
            self::assertFalse(ProductChangeFeed::isValidCursor($bad), $bad);
        }
    }

    public function testAnInvalidCursorIsRefusedWhenBuildingTheWindow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductChangeFeed::windowStart('ayer');
    }
}
