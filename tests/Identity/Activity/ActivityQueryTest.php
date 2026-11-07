<?php

declare(strict_types=1);

namespace App\Tests\Identity\Activity;

use App\Identity\Activity\ActivityFilter;
use App\Identity\Activity\ActivityQuery;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class ActivityQueryTest extends TestCase
{
    public function testSensitiveIsTheDefaultViewAndAllShowsEverything(): void
    {
        $query = new ActivityQuery($this->seeded());

        self::assertSame(3, $query->search(new ActivityFilter())['total']);
        self::assertSame(5, $query->search(new ActivityFilter(type: 'all'))['total']);
    }

    public function testGroupsFilterByEventTypePrefix(): void
    {
        $query = new ActivityQuery($this->seeded());

        self::assertSame(2, $query->search(new ActivityFilter(type: 'auth'))['total']);
        self::assertSame(2, $query->search(new ActivityFilter(type: 'user'))['total']);
        self::assertSame(1, $query->search(new ActivityFilter(type: 'role'))['total']);
        // an unknown type falls back to the sensitive view instead of widening to everything
        self::assertSame(3, $query->search(new ActivityFilter(type: 'nonsense'))['total']);
    }

    public function testNewestFirstWithStableOrderAndPagination(): void
    {
        $query = new ActivityQuery($this->seeded());

        $first = $query->search(new ActivityFilter(type: 'all'), page: 1, limit: 2);
        $second = $query->search(new ActivityFilter(type: 'all'), page: 2, limit: 2);

        self::assertSame(5, $first['total']);
        self::assertCount(2, $first['items']);
        self::assertSame('2026-11-07T15:00:00Z', $first['items'][0]['occurredAt']);
        self::assertCount(2, $second['items']);
        self::assertNotSame($first['items'][1]['id'], $second['items'][0]['id']);
        self::assertSame([], $query->search(new ActivityFilter(type: 'all'), page: 9, limit: 2)['items']);
    }

    public function testPageSizeIsCapped(): void
    {
        $db = ActivityTestDatabase::create();
        for ($i = 0; $i < 150; ++$i) {
            ActivityTestDatabase::insert($db, ['summary' => 's' . $i]);
        }

        self::assertCount(
            ActivityQuery::MAX_PAGE_SIZE,
            new ActivityQuery($db)->search(new ActivityFilter(type: 'all'), limit: 100000)['items'],
        );
    }

    public function testFiltersByActorAndDateRangeInclusive(): void
    {
        $query = new ActivityQuery($this->seeded());
        $utc = new DateTimeZone('UTC');

        self::assertSame(2, $query->search(new ActivityFilter(type: 'all', actor: 'ana'))['total']);
        $range = new ActivityFilter(
            type: 'all',
            fromUtc: new DateTimeImmutable('2026-11-07 10:00:00', $utc),
            toUtc: new DateTimeImmutable('2026-11-07 12:00:00', $utc),
        );
        self::assertSame(2, $query->search($range)['total']);
    }

    public function testSearchMatchesSummaryDetailSubjectAndActorAndTreatsWildcardsLiterally(): void
    {
        $db = $this->seeded();
        ActivityTestDatabase::insert($db, [
            'event_type' => 'user.created',
            'is_sensitive' => 1,
            'summary' => 'Descuento 100% aplicado',
            'subject' => 'x_y',
        ]);
        $query = new ActivityQuery($db);

        self::assertSame(1, $query->search(new ActivityFilter(type: 'all', search: 'Cajero'))['total'], 'summary');
        self::assertSame(1, $query->search(new ActivityFilter(type: 'all', search: 'Quitó'))['total'], 'detail');
        self::assertSame(
            1,
            $query->search(new ActivityFilter(type: 'all', search: 'x_y'))['total'],
            'subject, underscore literal',
        );
        self::assertSame(
            1,
            $query->search(new ActivityFilter(type: 'all', search: '100%'))['total'],
            'percent literal',
        );
        self::assertSame(
            1,
            $query->search(new ActivityFilter(type: 'all', search: '%'))['total'],
            'a lone % is a literal, not "match everything"',
        );
        self::assertSame(2, $query->search(new ActivityFilter(type: 'all', search: 'luis'))['total'], 'actor');
    }

    public function testSummaryCountsOnlyTypesThatHappenedInTheWindow(): void
    {
        $query = new ActivityQuery($this->seeded());

        $summary = $query->summary(new DateTimeImmutable('2026-11-07 00:00:00', new DateTimeZone('UTC')));

        $byType = array_column($summary, 'count', 'type');
        self::assertSame(
            ['auth.login' => 1, 'auth.login_failed' => 1, 'user.invited' => 1],
            $byType,
            'the 6 Nov role change is outside the window',
        );

        $wider = new ActivityQuery($this->seeded())->summary(
            new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('UTC')),
        );
        self::assertSame(1, array_column($wider, 'count', 'type')['role.permissions_changed']);
        self::assertSame('Inicios de sesión', $summary[0]['label']);
    }

    public function testActorsAreDistinctAlphabeticalAndSkipAnonymousEntries(): void
    {
        $actors = new ActivityQuery($this->seeded())->actors(
            new DateTimeImmutable('2020-01-01', new DateTimeZone('UTC')),
        );

        self::assertSame(['ana', 'luis'], $actors);
    }

    public function testExportIsCappedAndOrderedLikeTheList(): void
    {
        $rows = new ActivityQuery($this->seeded())->forExport(new ActivityFilter(type: 'all'));

        self::assertCount(5, $rows);
        self::assertSame('2026-11-07T15:00:00Z', $rows[0]['occurredAt']);
    }

    private function seeded(): Connection
    {
        $db = ActivityTestDatabase::create();
        $rows = [
            [
                'occurred_at' => '2026-11-07 15:00:00',
                'actor_username' => 'ana',
                'event_type' => 'auth.login',
                'is_sensitive' => 0,
                'summary' => 'ana inició sesión',
            ],
            [
                'occurred_at' => '2026-11-07 11:00:00',
                'actor_username' => 'luis',
                'event_type' => 'user.invited',
                'is_sensitive' => 1,
                'summary' => 'luis invitó a x@y como Cajero',
            ],
            [
                'occurred_at' => '2026-11-07 10:00:00',
                'actor_username' => null,
                'event_type' => 'auth.login_failed',
                'is_sensitive' => 1,
                'summary' => 'Intento de ingreso fallido',
            ],
            [
                'occurred_at' => '2026-11-06 09:00:00',
                'actor_username' => 'ana',
                'event_type' => 'role.permissions_changed',
                'is_sensitive' => 1,
                'summary' => 'Se cambiaron los permisos',
                'detail' => 'Quitó: Ver ventas',
            ],
            [
                'occurred_at' => '2026-11-05 09:00:00',
                'actor_username' => 'luis',
                'event_type' => 'user.created',
                'is_sensitive' => 0,
                'summary' => 'rutina',
            ],
        ];
        foreach ($rows as $row) {
            ActivityTestDatabase::insert($db, $row);
        }

        return $db;
    }
}
