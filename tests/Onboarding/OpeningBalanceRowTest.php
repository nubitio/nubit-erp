<?php

declare(strict_types=1);

namespace App\Tests\Onboarding;

use App\Onboarding\OpeningBalanceRow;
use PHPUnit\Framework\TestCase;

final class OpeningBalanceRowTest extends TestCase
{
    public function testParsesSpanishHeadersAndEuropeanAmount(): void
    {
        $row = OpeningBalanceRow::tryFrom([
            'RUC' => '20100100101',
            'Nombre' => 'Andina Retail SAC',
            'Monto' => '1.250,50',
            'Vencimiento' => '30/09/2026',
            'Número' => 'F001-88',
        ]);

        self::assertInstanceOf(OpeningBalanceRow::class, $row);
        self::assertSame('20100100101', $row->ruc);
        self::assertSame('Andina Retail SAC', $row->name);
        self::assertSame('1250.50', $row->amount);
        self::assertSame('2026-09-30', $row->dueDate->format('Y-m-d'));
        self::assertSame('F001-88', $row->number);
    }

    public function testRejectsMissingName(): void
    {
        $row = OpeningBalanceRow::tryFrom([
            'ruc' => '20100100101',
            'monto' => '10',
        ]);

        self::assertSame('Falta el nombre', $row);
    }
}
