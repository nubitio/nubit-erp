<?php

declare(strict_types=1);

namespace App\Reporting\TeamControl;

/**
 * Turns per-collaborator figures into plain-language "worth a look" signals.
 *
 * Deliberately simple and explainable: a person is compared with the REST of the team (never with
 * an average that includes them), every threshold has a minimum volume so one odd sale cannot flag
 * anyone, and each signal carries the sentence the owner reads. A signal is a prompt to review,
 * not a verdict. With a single seller there is nobody to compare against, so rate signals stay quiet.
 *
 * @phpstan-import-type Metrics from TeamControlQuery
 */
final class TeamControlRules
{
    /** A rate must be at least this many times the rest of the team's. */
    private const float RATIO = 2.0;

    private const int MIN_SALES_FOR_RATES = 5;
    private const int MIN_VOIDS = 3;
    private const float MIN_VOID_RATE = 0.03;
    private const int MIN_CREDIT_NOTES = 2;
    private const float MIN_CREDIT_NOTE_RATE = 0.03;
    private const float MIN_DISCOUNT_RATE = 0.03;
    private const int MIN_SHORT_SESSIONS = 2;
    private const float MIN_TOTAL_SHORTAGE = 20.0;

    /**
     * @param list<array{userId: int|null, name: string, metrics: Metrics}> $members
     *
     * @return list<array{
     *     userId: int|null, name: string, metrics: Metrics,
     *     voidRate: float, creditNoteRate: float, discountRate: float,
     *     level: 'ok'|'review',
     *     signals: list<array{key: string, label: string, detail: string}>,
     * }>
     */
    public function assess(array $members): array
    {
        $totals = ['sales' => 0, 'voids' => 0, 'creditNotes' => 0, 'discount' => 0.0, 'subtotal' => 0.0];
        foreach ($members as $member) {
            if ($member['userId'] === null) {
                continue;
            }
            $m = $member['metrics'];
            $totals['sales'] += $m['salesCount'];
            $totals['voids'] += $m['voidCount'];
            $totals['creditNotes'] += $m['creditNoteCount'];
            $totals['discount'] += $m['discountTotal'];
            $totals['subtotal'] += $m['subtotal'];
        }

        $assessed = [];
        foreach ($members as $member) {
            $m = $member['metrics'];
            $voidRate = self::rate($m['voidCount'], $m['salesCount']);
            $creditNoteRate = self::rate($m['creditNoteCount'], $m['salesCount']);
            $discountRate = self::ratio($m['discountTotal'], $m['subtotal']);

            $signals = [];
            if ($member['userId'] !== null) {
                // The rest of the team: totals minus this person.
                $restSales = $totals['sales'] - $m['salesCount'];
                $hasPeers = $restSales > 0;
                $enoughSales = $m['salesCount'] >= self::MIN_SALES_FOR_RATES;

                if ($hasPeers && $enoughSales && $m['voidCount'] >= self::MIN_VOIDS) {
                    $rest = self::rate($totals['voids'] - $m['voidCount'], $restSales);
                    if ($voidRate >= max(self::RATIO * $rest, self::MIN_VOID_RATE)) {
                        $signals[] = [
                            'key' => 'voids',
                            'label' => 'Anulaciones por encima del equipo',
                            'detail' => sprintf(
                                '%d anulaciones sobre %d ventas (%s). El resto del equipo anula %s.',
                                $m['voidCount'],
                                $m['salesCount'],
                                self::pct($voidRate),
                                self::pct($rest),
                            ),
                        ];
                    }
                }

                if ($hasPeers && $enoughSales && $m['creditNoteCount'] >= self::MIN_CREDIT_NOTES) {
                    $rest = self::rate($totals['creditNotes'] - $m['creditNoteCount'], $restSales);
                    if ($creditNoteRate >= max(self::RATIO * $rest, self::MIN_CREDIT_NOTE_RATE)) {
                        $signals[] = [
                            'key' => 'credit_notes',
                            'label' => 'Más notas de crédito que el equipo',
                            'detail' => sprintf(
                                '%d notas de crédito sobre %d ventas (%s). El resto del equipo emite %s.',
                                $m['creditNoteCount'],
                                $m['salesCount'],
                                self::pct($creditNoteRate),
                                self::pct($rest),
                            ),
                        ];
                    }
                }

                if ($hasPeers && $enoughSales) {
                    $restSubtotal = $totals['subtotal'] - $m['subtotal'];
                    $rest = self::ratio($totals['discount'] - $m['discountTotal'], $restSubtotal);
                    if ($discountRate >= max(self::RATIO * $rest, self::MIN_DISCOUNT_RATE)) {
                        $signals[] = [
                            'key' => 'discounts',
                            'label' => 'Descuentos por encima del equipo',
                            'detail' => sprintf(
                                'Descuenta %s de lo que vende (S/ %s). El resto del equipo descuenta %s.',
                                self::pct($discountRate),
                                number_format($m['discountTotal'], 2, '.', ','),
                                self::pct($rest),
                            ),
                        ];
                    }
                }

                // Cash shortages do not need a peer: an owner reads them on their own.
                if (
                    $m['cashShortCount'] >= self::MIN_SHORT_SESSIONS
                    || abs($m['cashShortage']) >= self::MIN_TOTAL_SHORTAGE
                ) {
                    $signals[] = [
                        'key' => 'cash_shortage',
                        'label' => 'Faltantes de caja',
                        'detail' => sprintf(
                            '%d cierre(s) con faltante, S/ %s en total.',
                            $m['cashShortCount'],
                            number_format(abs($m['cashShortage']), 2, '.', ','),
                        ),
                    ];
                }
            }

            $assessed[] = $member
            + [
                'voidRate' => $voidRate,
                'creditNoteRate' => $creditNoteRate,
                'discountRate' => $discountRate,
                'level' => $signals === [] ? 'ok' : 'review',
                'signals' => $signals,
            ];
        }

        return $assessed;
    }

    private static function rate(int $count, int $of): float
    {
        return $of > 0 ? $count / $of : 0.0;
    }

    private static function ratio(float $part, float $of): float
    {
        return $of > 0.0 ? $part / $of : 0.0;
    }

    private static function pct(float $rate): string
    {
        return number_format($rate * 100, 1, '.', '') . '%';
    }
}
