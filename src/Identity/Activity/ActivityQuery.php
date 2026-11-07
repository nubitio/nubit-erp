<?php

declare(strict_types=1);

namespace App\Identity\Activity;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/** Read side of the activity record. Read-only by construction: there is no write method here. */
final readonly class ActivityQuery
{
    public const int MAX_PAGE_SIZE = 100;
    public const int EXPORT_LIMIT = 5000;

    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function search(ActivityFilter $filter, int $page = 1, int $limit = 50): array
    {
        [$where, $params] = $this->where($filter);
        $limit = max(1, min($limit, self::MAX_PAGE_SIZE));
        $offset = (max(1, $page) - 1) * $limit;

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM identity_activity_event' . $where, $params);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, occurred_at, actor_username, event_type, is_sensitive, summary, detail, ip FROM identity_activity_event'
                . $where
                . sprintf(' ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d', $limit, $offset),
            $params,
        );

        return ['items' => array_map(self::present(...), $rows), 'total' => $total];
    }

    /** @return list<array<string, mixed>> newest first, capped at {@see EXPORT_LIMIT} */
    public function forExport(ActivityFilter $filter): array
    {
        [$where, $params] = $this->where($filter);

        return array_map(
            self::present(...),
            $this->connection->fetchAllAssociative(
                'SELECT id, occurred_at, actor_username, event_type, is_sensitive, summary, detail, ip FROM identity_activity_event'
                    . $where
                    . sprintf(' ORDER BY occurred_at DESC, id DESC LIMIT %d', self::EXPORT_LIMIT),
                $params,
            ),
        );
    }

    /** @return list<array{type: string, label: string, count: int}> counts per event type since the instant, only types that happened */
    public function summary(DateTimeImmutable $sinceUtc): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT event_type, COUNT(*) AS total FROM identity_activity_event WHERE occurred_at >= :since GROUP BY event_type', [
            'since' => $sinceUtc->format('Y-m-d H:i:s'),
        ]);
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['event_type']] = (int) $row['total'];
        }

        $result = [];
        foreach (ActivityType::all() as $type) {
            if (($counts[$type] ?? 0) > 0) {
                $result[] = ['type' => $type, 'label' => ActivityType::label($type), 'count' => $counts[$type]];
            }
        }

        return $result;
    }

    /** @return list<string> usernames that appear as actors since the instant, alphabetical */
    public function actors(DateTimeImmutable $sinceUtc): array
    {
        return array_map(strval(...), $this->connection->fetchFirstColumn(
            'SELECT DISTINCT actor_username FROM identity_activity_event WHERE actor_username IS NOT NULL AND occurred_at >= :since ORDER BY actor_username LIMIT 200',
            ['since' => $sinceUtc->format('Y-m-d H:i:s')],
        ));
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function where(ActivityFilter $filter): array
    {
        $conditions = [];
        $params = [];

        if (in_array($filter->type, ActivityType::GROUPS, strict: true)) {
            $conditions[] = 'event_type LIKE :group';
            $params['group'] = $filter->type . '.%';
        } elseif ($filter->type !== 'all') {
            // "sensitive", and also anything unrecognised: never widen to everything by accident.
            $conditions[] = 'is_sensitive = 1';
        }
        if ($filter->actor !== null && $filter->actor !== '') {
            $conditions[] = 'actor_username = :actor';
            $params['actor'] = $filter->actor;
        }
        if ($filter->fromUtc !== null) {
            $conditions[] = 'occurred_at >= :from';
            $params['from'] = $filter->fromUtc->format('Y-m-d H:i:s');
        }
        if ($filter->toUtc !== null) {
            $conditions[] = 'occurred_at <= :to';
            $params['to'] = $filter->toUtc->format('Y-m-d H:i:s');
        }
        if ($filter->search !== null && trim($filter->search) !== '') {
            // "!" as the escape character: unlike a backslash it means the same in MariaDB and SQLite.
            $like = '%' . preg_replace('/[!%_]/', '!$0', trim($filter->search)) . '%';
            $conditions[] = "(summary LIKE :q ESCAPE '!' OR detail LIKE :q ESCAPE '!' OR subject LIKE :q ESCAPE '!' OR actor_username LIKE :q ESCAPE '!')";
            $params['q'] = $like;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'occurredAt' => new DateTimeImmutable((string) $row['occurred_at'] . ' UTC')->format('Y-m-d\TH:i:s\Z'),
            'actor' => $row['actor_username'] === null ? null : (string) $row['actor_username'],
            'type' => (string) $row['event_type'],
            'sensitive' => (bool) $row['is_sensitive'],
            'summary' => (string) $row['summary'],
            'detail' => $row['detail'] === null ? null : (string) $row['detail'],
            'ip' => $row['ip'] === null ? null : (string) $row['ip'],
        ];
    }
}
