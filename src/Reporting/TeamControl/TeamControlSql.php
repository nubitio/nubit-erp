<?php

declare(strict_types=1);

namespace App\Reporting\TeamControl;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Shared plumbing of the "Control de caja y equipo" read models: the connection, the period and the codes. */
abstract readonly class TeamControlSql
{
    /** Outflows an operator can register by hand and that deserve a second look. */
    protected const array MANUAL_OUTFLOW_CODES = ['manager_withdrawal', 'petty_cash_expense', 'shortage_adjustment'];

    /** A close that is short by less than this is coin/rounding noise, not a shortage. */
    public const float CASH_TOLERANCE = 1.0;

    public function __construct(
        protected Connection $connection,
    ) {}

    /**
     * Runs a query with only the named parameters it actually uses (PDO rejects the unused ones).
     *
     * @param array<string, mixed>                       $params
     * @param array<string, ArrayParameterType> $types
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(string $sql, array $params, array $types = []): array
    {
        $used = array_filter(
            $params,
            static fn(string $name): bool => str_contains($sql, ':' . $name),
            ARRAY_FILTER_USE_KEY,
        );

        return $this->connection->fetchAllAssociative($sql, $used, array_intersect_key($types, $used));
    }

    /** @return array<string, mixed> */
    protected function params(string $branchId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [
            'branch' => $branchId,
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'from_ts' => self::utcStartOfDay($from),
            'to_ts' => self::utcStartOfDay($to->modify('+1 day')),
            'tolerance' => self::CASH_TOLERANCE,
        ];
    }

    private const string BUSINESS_TIMEZONE = 'America/Lima';

    /**
     * Timestamps are stored in UTC, but a period is a span of the shop's days: this is the UTC
     * instant at which the given business day starts.
     */
    private static function utcStartOfDay(DateTimeImmutable $day): string
    {
        return new DateTimeImmutable($day->format('Y-m-d') . ' 00:00:00', new DateTimeZone(self::BUSINESS_TIMEZONE))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /** A stored UTC timestamp as the shop's local wall-clock time, which is what the owner reads. */
    protected static function toBusinessTime(string $utc): string
    {
        return new DateTimeImmutable($utc, new DateTimeZone('UTC'))
            ->setTimezone(new DateTimeZone(self::BUSINESS_TIMEZONE))
            ->format('Y-m-d H:i:s');
    }
}
