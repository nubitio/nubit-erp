<?php

declare(strict_types=1);

namespace App\Tests\Reporting\TeamControl;

use App\Reporting\TeamControl\TeamControlRules;
use PHPUnit\Framework\TestCase;

final class TeamControlRulesTest extends TestCase
{
    public function testAQuietTeamHasNothingToReview(): void
    {
        $result = new TeamControlRules()->assess([
            $this->member(1, sales: 40, voids: 1, subtotal: 4000.0, discount: 40.0),
            $this->member(2, sales: 38, voids: 1, subtotal: 3800.0, discount: 38.0),
        ]);

        self::assertSame(['ok', 'ok'], array_column($result, 'level'));
    }

    public function testFlagsAnOperatorWhoVoidsFarMoreThanThePeers(): void
    {
        $result = new TeamControlRules()->assess([
            $this->member(1, sales: 40, voids: 1),
            $this->member(2, sales: 30, voids: 8),
        ]);

        self::assertSame('ok', $result[0]['level']);
        self::assertSame('review', $result[1]['level']);
        self::assertSame(['voids'], array_column($result[1]['signals'], 'key'));
        self::assertStringContainsString('8 anulaciones sobre 30 ventas', $result[1]['signals'][0]['detail']);
    }

    public function testFlagsDiscountsWellAboveThePeers(): void
    {
        $result = new TeamControlRules()->assess([
            $this->member(1, sales: 40, subtotal: 4000.0, discount: 40.0),
            $this->member(2, sales: 30, subtotal: 3000.0, discount: 450.0),
        ]);

        self::assertSame(['discounts'], array_column($result[1]['signals'], 'key'));
    }

    public function testASingleSellerIsNeverComparedAgainstNobody(): void
    {
        $result = new TeamControlRules()->assess([
            $this->member(1, sales: 30, voids: 9, creditNotes: 5, subtotal: 3000.0, discount: 600.0),
        ]);

        self::assertSame('ok', $result[0]['level']);
        self::assertSame([], $result[0]['signals']);
    }

    public function testTooFewVoidsOrSalesNeverFlagAnyone(): void
    {
        $fewVoids = new TeamControlRules()->assess([
            $this->member(1, sales: 40, voids: 0),
            $this->member(2, sales: 30, voids: 2),
        ]);
        $fewSales = new TeamControlRules()->assess([
            $this->member(1, sales: 40, voids: 0),
            $this->member(2, sales: 4, voids: 3),
        ]);

        self::assertSame('ok', $fewVoids[1]['level']);
        self::assertSame('ok', $fewSales[1]['level']);
    }

    public function testCashShortagesFlagEvenWithoutPeers(): void
    {
        $repeated = new TeamControlRules()->assess([$this->member(1, sales: 10, shortCount: 2, shortage: -6.0)]);
        $large = new TeamControlRules()->assess([$this->member(1, sales: 10, shortCount: 1, shortage: -35.0)]);
        $small = new TeamControlRules()->assess([$this->member(1, sales: 10, shortCount: 1, shortage: -3.0)]);

        self::assertSame(['cash_shortage'], array_column($repeated[0]['signals'], 'key'));
        self::assertSame(['cash_shortage'], array_column($large[0]['signals'], 'key'));
        self::assertSame('ok', $small[0]['level']);
    }

    public function testUnattributedRecordsAreNeverFlaggedAndDoNotSkewThePeers(): void
    {
        $result = new TeamControlRules()->assess([
            $this->member(null, sales: 0, voids: 50),
            $this->member(1, sales: 40, voids: 1),
            $this->member(2, sales: 40, voids: 1),
        ]);

        self::assertSame(['ok', 'ok', 'ok'], array_column($result, 'level'));
    }

    /**
     * @return array{
     *     userId: int|null, name: string,
     *     metrics: array{
     *         salesCount: int, salesTotal: float, subtotal: float, discountTotal: float,
     *         voidCount: int, voidTotal: float, creditNoteCount: int, creditNoteTotal: float,
     *         cashSessions: int, cashShortCount: int, cashShortage: float, cashSurplus: float,
     *         manualOutflowCount: int, manualOutflowTotal: float,
     *     },
     * }
     */
    private function member(
        ?int $id,
        int $sales = 0,
        int $voids = 0,
        int $creditNotes = 0,
        float $subtotal = 0.0,
        float $discount = 0.0,
        int $shortCount = 0,
        float $shortage = 0.0,
    ): array {
        return [
            'userId' => $id,
            'name' => $id === null ? 'Sin autor registrado' : 'Usuario ' . $id,
            'metrics' => [
                'salesCount' => $sales,
                'salesTotal' => $subtotal - $discount,
                'subtotal' => $subtotal,
                'discountTotal' => $discount,
                'voidCount' => $voids,
                'voidTotal' => $voids * 20.0,
                'creditNoteCount' => $creditNotes,
                'creditNoteTotal' => $creditNotes * 20.0,
                'cashSessions' => 0,
                'cashShortCount' => $shortCount,
                'cashShortage' => $shortage,
                'cashSurplus' => 0.0,
                'manualOutflowCount' => 0,
                'manualOutflowTotal' => 0.0,
            ],
        ];
    }
}
