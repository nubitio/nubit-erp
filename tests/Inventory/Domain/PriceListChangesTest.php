<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Domain;

use App\Inventory\Domain\PriceListChanges;
use App\Inventory\Dto\PriceItemDto;
use PHPUnit\Framework\TestCase;

final class PriceListChangesTest extends TestCase
{
    public function testAChangedPriceShowsBeforeAndAfterUnderTheProductName(): void
    {
        $changes = PriceListChanges::diff(
            ['p1' => '9.8000'],
            [new PriceItemDto('p1', '10.4000')],
            ['p1' => 'POLLO ENTERO X KG'],
        );

        self::assertSame(['POLLO ENTERO X KG' => ['before' => '9.8000', 'after' => '10.4000']], $changes);
    }

    public function testAFirstSpecialPriceHasNoBefore(): void
    {
        $changes = PriceListChanges::diff([], [new PriceItemDto('p1', '10.4')], ['p1' => 'POLLO']);

        self::assertSame(['POLLO' => ['before' => null, 'after' => '10.4000']], $changes);
    }

    public function testAnUnchangedPriceIsNotReportedEvenIfWrittenWithAnotherScale(): void
    {
        self::assertSame([], PriceListChanges::diff(['p1' => '10.4000'], [new PriceItemDto('p1', '10.40')], []));
    }

    public function testFallsBackToTheProductIdWhenTheNameIsUnknown(): void
    {
        $changes = PriceListChanges::diff([], [new PriceItemDto('p9', '5')], []);

        self::assertArrayHasKey('p9', $changes);
    }

    public function testIgnoresRowsWithoutProductOrPrice(): void
    {
        self::assertSame(
            [],
            PriceListChanges::diff([], [new PriceItemDto(null, '5'), new PriceItemDto('p1', null)], []),
        );
    }
}
