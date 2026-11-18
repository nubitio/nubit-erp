<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Application\Query;

use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\TenantCapabilityResolver;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class CashFlowQuery
{
    private const int PROJECTION_DAYS = 30;

    public function __construct(
        private Connection $connection,
        private ?TenantCapabilityResolver $capabilities = null,
    ) {}

    /** With Treasury on, money in and out comes from the money accounts (one currency); otherwise from the legacy sources. */
    public function usesTreasury(): bool
    {
        return $this->capabilities?->isEnabled(CapabilityKey::TreasuryAccounts) === true;
    }

    /** @return array<string, mixed> */
    public function compute(string $from = '', string $to = '', string $currency = 'PEN'): array
    {
        if ('' === $from) {
            $from = date('Y-m-01');
        }
        if ('' === $to) {
            $to = date('Y-m-d');
        }

        $treasury = $this->usesTreasury();
        $currency = strtoupper($currency);

        return [
            'from' => $from,
            'to' => $to,
            'source' => $treasury ? 'treasury' : 'legacy',
            'currency' => $treasury ? $currency : null,
            'real' => $treasury ? $this->computeTreasury($from, $to, $currency) : $this->computeReal($from, $to),
            'proyectado' => $this->computeProyectado($treasury ? $currency : null),
        ];
    }

    /** @return list<array{weekKey: string, entradas: float, salidas: float, neto: float}> */
    private function computeTreasury(string $from, string $to, string $currency): array
    {
        $toExclusive = new DateTimeImmutable($to)
            ->modify('+1 day')
            ->format('Y-m-d');
        $weeks = new TreasuryCashFlow($this->connection)->weeks($from, $toExclusive, $currency);
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

    /** @return list<array{weekKey: string, entradas: float, salidas: float, neto: float}> */
    private function computeReal(string $from, string $to): array
    {
        $weeks = [];
        $toExclusive = new DateTimeImmutable($to)
            ->modify('+1 day')
            ->format('Y-m-d');

        // Bucket by ISO year-week ('%x-%v') rather than DATE_SUB(..., INTERVAL
        // ... DAY) — the latter isn't valid SQLite syntax, and DATE_FORMAT is
        // the one date function this codebase already shims for SQLite-backed
        // unit tests (see TaxSummaryQueryTest).
        $inflows = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(paid_at, '%x-%v') AS week_key, SUM(amount) AS monto
             FROM sale_payments
             WHERE paid_at IS NOT NULL AND paid_at >= :from AND paid_at < :toExclusive
             GROUP BY week_key", [
            'from' => $from,
            'toExclusive' => $toExclusive,
        ]);
        foreach ($inflows as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'entradas', (float) $row['monto']);
        }

        $outflows = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(paid_at, '%x-%v') AS week_key, SUM(amount) AS monto
             FROM supplier_payment
             WHERE paid_at >= :from AND paid_at < :toExclusive
             GROUP BY week_key", [
            'from' => $from,
            'toExclusive' => $toExclusive,
        ]);
        foreach ($outflows as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'salidas', (float) $row['monto']);
        }

        $cashMovements = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(created_at, '%x-%v') AS week_key, movement_type, SUM(amount) AS monto
             FROM cash_movements
             WHERE created_at >= :from AND created_at < :toExclusive
               AND movement_type IN ('CASH_IN', 'CASH_OUT', 'REFUND_OUT')
             GROUP BY week_key, movement_type", [
            'from' => $from,
            'toExclusive' => $toExclusive,
        ]);
        foreach ($cashMovements as $row) {
            $bucket = 'CASH_IN' === $row['movement_type'] ? 'entradas' : 'salidas';
            $this->addToWeek($weeks, (string) $row['week_key'], $bucket, (float) $row['monto']);
        }

        $reimbursements = $this->connection->fetchAllAssociative("SELECT DATE_FORMAT(reimbursed_at, '%x-%v') AS week_key, SUM(amount) AS monto
             FROM expense_report
             WHERE status = 'REIMBURSED'
               AND reimbursed_at >= :from AND reimbursed_at < :toExclusive
             GROUP BY week_key", [
            'from' => $from,
            'toExclusive' => $toExclusive,
        ]);
        foreach ($reimbursements as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'salidas', (float) $row['monto']);
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
    private function computeProyectado(?string $currency = null): array
    {
        $filter = $currency === null ? '' : ' AND currency = :currency';
        $today = new DateTimeImmutable('today')->format('Y-m-d');
        $limit = new DateTimeImmutable('today')
            ->modify('+' . self::PROJECTION_DAYS . ' days')
            ->format('Y-m-d');
        $weeks = [];

        $ar = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(due_date, '%x-%v') AS week_key, SUM(balance_due) AS monto
             FROM sales
             WHERE due_date BETWEEN :today AND :limit
               AND balance_due > 0
               AND status != 'VOIDED'" . $filter . '
             GROUP BY week_key',
            $this->projectionParams($today, $limit, $currency),
        );
        foreach ($ar as $row) {
            $this->addToWeek($weeks, (string) $row['week_key'], 'cobrosEsperados', (float) $row['monto']);
        }

        $ap = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(due_date, '%x-%v') AS week_key, SUM(balance_due) AS monto
             FROM supplier_bill
             WHERE due_date BETWEEN :today AND :limit
               AND balance_due > 0
               AND status != 'VOID'" . $filter . '
             GROUP BY week_key',
            $this->projectionParams($today, $limit, $currency),
        );
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

    /** @return array<string, string> */
    private function projectionParams(string $today, string $limit, ?string $currency): array
    {
        return ['today' => $today, 'limit' => $limit] + ($currency === null ? [] : ['currency' => $currency]);
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
