<?php

declare(strict_types=1);

namespace App\Tests\Billing\Dto;

use App\Billing\Dto\SeriesResultDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeriesResultDto::class)]
final class SeriesResultDtoTest extends TestCase
{
    public function testConstructorSetsSeries(): void
    {
        $dto = new SeriesResultDto(series: 'F001', associatedSeries: 'BC01');

        self::assertSame('F001', $dto->series);
    }

    public function testConstructorSetsAssociatedSeries(): void
    {
        $dto = new SeriesResultDto(series: 'F001', associatedSeries: 'BC01');

        self::assertSame('BC01', $dto->associatedSeries);
    }

    public function testPropertiesArePubliclyReadable(): void
    {
        $dto = new SeriesResultDto(series: 'B001', associatedSeries: 'BB01');

        self::assertSame('B001', $dto->series);
        self::assertSame('BB01', $dto->associatedSeries);
    }
}
