<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Application\Query;

use Doctrine\DBAL\Connection;

/**
 * Money that really came in and went out, per week, in one currency, taken from where the money is recorded:
 * the movements of banks and wallets, plus the drawers' own ledger. Moving money between the business's own
 * accounts, opening balances and reclassifications are not flows, so they are left out. Because this is the
 * same record the owner's summary uses, the two can never disagree.
 */
final readonly class TreasuryCashFlow
{
    /** Sources that move money without any coming in or going out of the business. */
    private const string NOT_A_FLOW = "'transfer', 'opening_balance', 'reclassification'";

    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, array{entradas: float, salidas: float}> by ISO year-week */
    public function weeks(string $from, string $toExclusive, string $currency): array
    {
        $weeks = [];
        $params = ['from' => $from, 'toExclusive' => $toExclusive, 'currency' => $currency];

        $accounts = $this->connection->fetchAllAssociative(sprintf(
            "SELECT DATE_FORMAT(m.occurred_at, '%%x-%%v') AS week_key,
                        SUM(CASE WHEN m.signed_amount > 0 THEN m.signed_amount ELSE 0 END) AS inflow,
                        SUM(CASE WHEN m.signed_amount < 0 THEN -m.signed_amount ELSE 0 END) AS outflow
                 FROM treasury_movement m
                 JOIN treasury_account a ON a.id = m.account_id
                 WHERE a.kind <> 'cash_register' AND m.currency = :currency
                   AND m.occurred_at >= :from AND m.occurred_at < :toExclusive
                   AND m.source_type NOT IN (%s)
                 GROUP BY week_key",
            self::NOT_A_FLOW,
        ), $params);
        $drawers = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(cm.created_at, '%x-%v') AS week_key,
                    SUM(CASE WHEN cm.movement_type IN ('SALE_IN', 'CASH_IN') THEN cm.amount ELSE 0 END) AS inflow,
                    SUM(CASE WHEN cm.movement_type IN ('REFUND_OUT', 'CASH_OUT') THEN cm.amount ELSE 0 END) AS outflow
             FROM cash_movements cm
             JOIN cash_sessions s ON s.id = cm.cash_session_id
             JOIN treasury_account a ON a.cash_register_id = s.cash_register_id
             WHERE a.currency = :currency
               AND cm.created_at >= :from AND cm.created_at < :toExclusive
             GROUP BY week_key", $params);

        foreach ([...$accounts, ...$drawers] as $row) {
            $key = (string) $row['week_key'];
            $weeks[$key] ??= ['entradas' => 0.0, 'salidas' => 0.0];
            $weeks[$key]['entradas'] += (float) $row['inflow'];
            $weeks[$key]['salidas'] += (float) $row['outflow'];
        }

        return $weeks;
    }
}
