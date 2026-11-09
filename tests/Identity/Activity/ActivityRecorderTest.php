<?php

declare(strict_types=1);

namespace App\Tests\Identity\Activity;

use App\Identity\Activity\ActivityRecorder;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ActivityRecorderTest extends TestCase
{
    public function testStoresTheEntryWithActorRequestContextAndSensitivity(): void
    {
        $db = ActivityTestDatabase::create();
        $recorder = $this->recorder(
            $db,
            user: $this->user(7, 'ana'),
            ip: '203.0.113.9',
            userAgent: 'Mozilla/5.0 Chrome/120',
        );

        $recorder->record(
            ActivityType::ROLE_PERMISSIONS_CHANGED,
            'Se cambiaron los permisos del rol Cajero',
            'Agregó: Ver ventas',
            'Cajero',
        );

        $row = $db->fetchAssociative('SELECT * FROM identity_activity_event');
        self::assertIsArray($row);
        self::assertSame('role.permissions_changed', $row['event_type']);
        self::assertSame(1, (int) $row['is_sensitive']);
        self::assertSame(7, (int) $row['actor_user_id']);
        self::assertSame('ana', $row['actor_username']);
        self::assertSame('203.0.113.9', $row['ip']);
        self::assertSame('Mozilla/5.0 Chrome/120', $row['user_agent']);
        self::assertSame('Cajero', $row['subject']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['occurred_at']);
    }

    public function testRoutineEventsAreNotFlaggedSensitive(): void
    {
        $db = ActivityTestDatabase::create();
        $this->recorder($db)->record(ActivityType::LOGIN, 'ana inició sesión');

        self::assertSame(0, (int) $db->fetchOne('SELECT is_sensitive FROM identity_activity_event'));
    }

    public function testExplicitActorWinsAndAStringActorKeepsOnlyTheName(): void
    {
        $db = ActivityTestDatabase::create();
        $recorder = $this->recorder($db, user: $this->user(1, 'signed-in'));

        $recorder->record(ActivityType::LOGIN, 'a', actor: $this->user(9, 'explicit'));
        $recorder->record(ActivityType::LOGIN_FAILED, 'b', actor: 'typed-name');

        $rows = $db->fetchAllAssociative(
            'SELECT actor_user_id, actor_username FROM identity_activity_event ORDER BY id',
        );
        self::assertSame([9, 'explicit'], [(int) $rows[0]['actor_user_id'], $rows[0]['actor_username']]);
        self::assertNull($rows[1]['actor_user_id']);
        self::assertSame('typed-name', $rows[1]['actor_username']);
    }

    public function testControlCharactersAreStrippedAndLengthIsCapped(): void
    {
        $db = ActivityTestDatabase::create();
        $this->recorder($db)->record(
            ActivityType::LOGIN_FAILED,
            "evil\r\nINJECTED\x00 line " . str_repeat('x', 400),
            subject: "  \n ",
        );

        $row = $db->fetchAssociative('SELECT summary, subject FROM identity_activity_event');
        self::assertIsArray($row);
        self::assertStringNotContainsString("\n", (string) $row['summary']);
        self::assertStringNotContainsString("\0", (string) $row['summary']);
        self::assertSame(255, mb_strlen((string) $row['summary']));
        self::assertNull($row['subject'], 'a blank value is stored as NULL');
    }

    public function testFailedSignInsFromOneAddressAreCappedPerMinute(): void
    {
        $db = ActivityTestDatabase::create();
        $recorder = $this->recorder($db, ip: '198.51.100.1');

        for ($i = 0; $i < 25; ++$i) {
            $recorder->record(ActivityType::LOGIN_FAILED, 'attempt ' . $i);
        }
        $this->recorder($db, ip: '198.51.100.2')->record(ActivityType::LOGIN_FAILED, 'other address');
        for ($i = 0; $i < 25; ++$i) {
            $recorder->record(ActivityType::LOGIN, 'successful sign-ins are never capped');
        }

        self::assertSame(
            10,
            (int) $db->fetchOne(
                "SELECT COUNT(*) FROM identity_activity_event WHERE ip = '198.51.100.1' AND event_type = 'auth.login_failed'",
            ),
        );
        self::assertSame(
            1,
            (int) $db->fetchOne("SELECT COUNT(*) FROM identity_activity_event WHERE ip = '198.51.100.2'"),
        );
        self::assertSame(
            25,
            (int) $db->fetchOne("SELECT COUNT(*) FROM identity_activity_event WHERE event_type = 'auth.login'"),
        );
    }

    public function testNeverThrowsWhenTheDatabaseFailsAndLogsInstead(): void
    {
        $db = ActivityTestDatabase::create();
        $db->executeStatement('DROP TABLE identity_activity_event');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::stringContains('could not be recorded'));
        $recorder = new ActivityRecorder($db, $this->createStub(Security::class), new RequestStack(), $logger);

        $recorder->record(ActivityType::USER_CREATED, 'must not break the caller');

        $this->addToAssertionCount(1); // reaching this line is the assertion: nothing was thrown
    }

    private function recorder(
        Connection $db,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): ActivityRecorder {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $requests = new RequestStack();
        $request = Request::create('/', server: array_filter(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $userAgent]));
        $requests->push($request);

        return new ActivityRecorder($db, $security, $requests, $this->createStub(LoggerInterface::class));
    }

    private function user(int $id, string $username): User
    {
        $user = new User()
            ->setUsername($username)
            ->setRole(new Role());
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
