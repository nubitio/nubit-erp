<?php

declare(strict_types=1);

namespace App\Tests\Identity\Activity;

use App\Identity\Activity\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvCellTest extends TestCase
{
    #[DataProvider('cells')]
    public function testSafe(mixed $input, string $expected): void
    {
        self::assertSame($expected, CsvCell::safe($input));
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function cells(): iterable
    {
        yield 'formula' => ['=SUM(1+1)', "'=SUM(1+1)"];
        yield 'plus' => ['+1234', "'+1234"];
        yield 'minus' => ['-2+3', "'-2+3"];
        yield 'at' => ['@SUM(A1)', "'@SUM(A1)"];
        yield 'tab' => ["\t=1", "'\t=1"];
        yield 'plain text is untouched' => ['ana inició sesión', 'ana inició sesión'];
        yield 'a dash inside is fine' => ['Caja-1', 'Caja-1'];
        yield 'empty' => ['', ''];
        yield 'null' => [null, ''];
        yield 'number' => [42, '42'];
        yield 'array is dropped' => [['x'], ''];
    }
}
