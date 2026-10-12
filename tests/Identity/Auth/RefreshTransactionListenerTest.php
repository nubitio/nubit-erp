<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\RefreshTransactionListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RefreshTransactionListenerTest extends TestCase
{
    #[DataProvider('statuses')]
    public function testRotationCommitsOnlyOnSuccess(int $status, string $outcome): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method($outcome);
        $connection->expects(static::never())->method($outcome === 'commit' ? 'rollBack' : 'commit');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $manager->expects($outcome === 'rollBack' ? static::once() : static::never())->method('clear');
        $request = Request::create('/api/auth/refresh', method: 'POST');
        $request->attributes->set('_route', 'nubit_admin_auth_refresh');
        $event = new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn(): JsonResponse => new JsonResponse(status: $status),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
        (new RefreshTransactionListener($manager))($event);
        static::assertSame($status, $event->getController()($request)->getStatusCode());
    }

    public static function statuses(): iterable
    {
        yield 'renewed credentials' => [200, 'commit'];
        yield 'token generation failure' => [500, 'rollBack'];
        yield 'invalid credentials' => [401, 'rollBack'];
    }

    public function testUnhandledFailureRollsBackConsumedToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method('rollBack');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $manager->expects(static::once())->method('clear');
        $request = Request::create('/api/auth/refresh', method: 'POST');
        $request->attributes->set('_route', 'nubit_admin_auth_refresh');
        $event = new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn() => throw new RuntimeException('generation failed'),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
        (new RefreshTransactionListener($manager))($event);
        $this->expectException(RuntimeException::class);
        $event->getController()($request);
    }
}
