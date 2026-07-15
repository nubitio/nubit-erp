<?php

declare(strict_types=1);

namespace App\Tests\Identity\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\UserNotification;
use App\Identity\Repository\UserNotificationRepository;
use App\Identity\Security\Security;
use App\Identity\State\UserNotificationProvider;
use App\Identity\State\UserNotificationReadAllProcessor;
use App\Identity\State\UserNotificationReadProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class UserNotificationStateTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeNotification(int $userId = 1, bool $read = false): UserNotification
    {
        $n = new UserNotification();
        $n->setUserId($userId);
        $n->setType('info');
        $n->setSeverity('low');
        $n->setTitle('Test');
        $n->setBody('Body');

        if ($read) {
            $n->markRead();
        }

        return $n;
    }

    // ── UserNotificationReadProcessor ─────────────────────────────────────────

    public function testReadProcessorThrowsWhenUnauthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $processor = new UserNotificationReadProcessor($em, $security);

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process($this->makeNotification(), new Patch(), ['id' => 1]);
    }

    public function testReadProcessorThrowsWhenNotificationNotFound(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        $processor = new UserNotificationReadProcessor($em, $security);

        $this->expectException(NotFoundHttpException::class);
        $processor->process($this->makeNotification(), new Patch(), ['id' => 99]);
    }

    public function testReadProcessorThrowsWhenUserDoesNotOwnNotification(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 1, username: 'admin'));

        $notification = $this->makeNotification(userId: 999); // owned by user 999
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($notification);

        $processor = new UserNotificationReadProcessor($em, $security);

        $this->expectException(AccessDeniedHttpException::class);
        $processor->process($notification, new Patch(), ['id' => 1]);
    }

    public function testReadProcessorMarksUnreadNotificationAsRead(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 1, username: 'admin'));

        $notification = $this->makeNotification(userId: 1, read: false);
        self::assertFalse($notification->isRead());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($notification);
        $em->expects(self::once())->method('flush');

        $processor = new UserNotificationReadProcessor($em, $security);
        $result = $processor->process($notification, new Patch(), ['id' => 1]);

        self::assertSame($notification, $result);
        self::assertTrue($result->isRead());
    }

    public function testReadProcessorSkipsFlushWhenAlreadyRead(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 1, username: 'admin'));

        $notification = $this->makeNotification(userId: 1, read: true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($notification);
        $em->expects(self::never())->method('flush'); // already read → no flush

        $processor = new UserNotificationReadProcessor($em, $security);
        $result = $processor->process($notification, new Patch(), ['id' => 1]);

        self::assertTrue($result->isRead());
    }

    public function testReadAllProcessorDelegatesBulkUpdateToRepository(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'admin'));

        $notifications = $this->createMock(UserNotificationRepository::class);
        $notifications
            ->expects(self::once())
            ->method('markAllUnreadAsRead')
            ->with(7, self::isInstanceOf(\DateTimeImmutable::class));

        $processor = new UserNotificationReadAllProcessor($notifications, $security);

        $processor->process(null, new Post());
    }

    public function testReadAllProcessorThrowsWhenUnauthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $notifications = $this->createMock(UserNotificationRepository::class);
        $notifications->expects(self::never())->method('markAllUnreadAsRead');

        $processor = new UserNotificationReadAllProcessor($notifications, $security);

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process(null, new Post());
    }

    // ── UserNotificationProvider ──────────────────────────────────────────────

    public function testProviderReturnsEmptyArrayWhenUnauthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $provider = new UserNotificationProvider($em, $security);

        $result = $provider->provide(new GetCollection());
        self::assertSame([], $result);
    }

    public function testProviderReturnsNotificationsForAuthenticatedUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $expected = [$this->makeNotification(1), $this->makeNotification(1)];

        $query = $this->createStub(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('setMaxResults')->willReturnSelf();
        $query->method('getResult')->willReturn($expected);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($query);

        $provider = new UserNotificationProvider($em, $security);
        $result = $provider->provide(new GetCollection());

        self::assertCount(2, $result);
        self::assertSame($expected[0], $result[0]);
    }
}
