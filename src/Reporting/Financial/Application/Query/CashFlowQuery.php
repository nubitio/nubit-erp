<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Application\Query;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class CashFlowQuery
{
    private const int PROJECTION_DAYS = 30;

    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed> */
    public function compute(string $from = '', string $to = ''): array
    {
        if ('' === $from) {
            $from = date('Y-m-01');
        }
        if ('' === $to) {
            $to = date('Y-m-d');
        }

        return [
            'from' => $from,
            'to' => $to,
            'real' => $this->computeReal($from, $to),
            'proyectado' => $this->computeProyectado(),
        ];
    }

    /** @return list<array{weekKey: string, entradas: float, salidas: float, neto: float}> */
    private function computeReal(string $from, string $to): array
    {
        $weeks = [];

        // Bucket by ISO year-week ('%x-%v') rather than DATE_SUB(..., INTERVAL
        // ... DAY) — the latter isn't valid SQLite syntax, and DATE_FORMAT is
        // the one date function this codebase already shims for SQLite-backed
        // unit tests (see TaxSummaryQueryTest).
        $inflows = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(paid_at, '%x-%v') AS week_key, SUM(amount) AS monto
             FROM sale_payments
             WHERE paid_at IS NOT NULL AND paid_at BETWEEN :from AND :to
             GROUP BY week_key", [
            'from' => $from,
            'to' => $to,
        ]);
        foreach ($inflows as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'entradas', (float) $row['monto']);
        }

        $outflows = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(paid_at, '%x-%v') AS week_key, SUM(amount) AS monto
             FROM supplier_payment
             WHERE paid_at BETWEEN :from AND :to
             GROUP BY week_key", [
            'from' => $from,
            'to' => $to,
        ]);
        foreach ($outflows as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'salidas', (float) $row['monto']);
        }

        $cashMovements = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(created_at, '%x-%v') AS week_key, type, SUM(amount) AS monto
             FROM cash_movement
             WHERE created_at BETWEEN :from AND :to
             GROUP BY week_key, type", [
            'from' => $from,
            'to' => $to,
        ]);
        foreach ($cashMovements as $row) {
            $bucket = 'income' === $row['type'] ? 'entradas' : 'salidas';
            $this->addToWeek($weeks, (string) $row['week_key'], $bucket, (float) $row['monto']);
        }

        ksort($weeks);

        return array_map(
            static fn(string $weekKey, array $w): array => [
                'weekKey' => $weekKey,
                'entradas' => $w['entradas'],
                'salidas' => $w['salidas'],
                'neto' => $w['entradas'] - $w['salidas'],
            ],
            array_keys($weeks),
            $weeks,
        );
    }

    /** @return list<array{weekKey: string, cobrosEsperados: float, pagosEsperados: float, netoEsperado: float}> */
    private function computeProyectado(): array
    {
        $today = new DateTimeImmutable('today')->format('Y-m-d');
        $limit = new DateTimeImmutable('today')
            ->modify('+' . self::PROJECTION_DAYS . ' days')
            ->format('Y-m-d');
        $weeks = [];

        $ar = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(due_date, '%x-%v') AS week_key, SUM(balance_due) AS monto
             FROM sales
             WHERE due_date BETWEEN :today AND :limit
               AND balance_due > 0
               AND status != 'VOIDED'
             GROUP BY week_key", [
            'today' => $today,
            'limit' => $limit,
        ]);
        foreach ($ar as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'cobrosEsperados', (float) $row['monto']);
        }

        $ap = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(due_date, '%x-%v') AS week_key, SUM(balance_due) AS monto
             FROM supplier_bill
             WHERE due_date BETWEEN :today AND :limit
               AND balance_due > 0
               AND status != 'VOID'
             GROUP BY week_key", [
            'today' => $today,
            'limit' => $limit,
        ]);
        foreach ($ap as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'pagosEsperados', (float) $row['monto']);
        }

        ksort($weeks);

        return array_map(
            static fn(string $weekKey, array $w): array => [
                'weekKey' => $weekKey,
                'cobrosEsperados' => $w['cobrosEsperados'],
                'pagosEsperados' => $w['pagosEsperados'],
                'netoEsperado' => $w['cobrosEsperados'] - $w['pagosEsperados'],
            ],
            array_keys($weeks),
            $weeks,
        );
    }

    /**
     * @param array<string, array<string, float>> $weeks
     */
    private function addToWeek(array &$weeks, string $weekKey, string $bucket, float $amount): void
    {
        $weeks[$weekKey] ??= ['entradas' => 0.0, 'salidas' => 0.0, 'cobrosEsperados' => 0.0, 'pagosEsperados' => 0.0];
        $weeks[$weekKey][$bucket] += $amount;
    }
}
