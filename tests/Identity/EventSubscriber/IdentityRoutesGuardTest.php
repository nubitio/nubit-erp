<?php

declare(strict_types=1);

namespace App\Tests\Identity\EventSubscriber;

use App\Identity\EventSubscriber\IdentityRoutesGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class IdentityRoutesGuardTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function publicIdentityPaths(): iterable
    {
        yield 'forgot' => ['/api/auth/password/forgot'];
        yield 'reset' => ['/api/auth/password/reset'];
        yield 'invitation preview' => ['/api/invitations/abc123'];
        yield 'invitation accept' => ['/api/invitations/abc123/accept'];
    }

    #[DataProvider('publicIdentityPaths')]
    public function testAnswersNotFoundWhileTheModuleIsOff(string $path): void
    {
        $event = $this->event($path);
        new IdentityRoutesGuard(false)->onRequest($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
    }

    #[DataProvider('publicIdentityPaths')]
    public function testDoesNothingWhileTheModuleIsOn(string $path): void
    {
        $event = $this->event($path);
        new IdentityRoutesGuard(true)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    /** @return iterable<string, array{string}> */
    public static function untouchedPaths(): iterable
    {
        yield 'login' => ['/api/auth/login'];
        yield 'admin invite (authenticated, not public)' => ['/api/invitations'];
        yield 'totp' => ['/api/auth/totp'];
        yield 'users' => ['/api/users'];
        yield 'nested invitation path' => ['/api/invitations/abc/other/accept'];
    }

    #[DataProvider('untouchedPaths')]
    public function testNeverTouchesOtherRoutesEvenWhenOff(string $path): void
    {
        $event = $this->event($path);
        new IdentityRoutesGuard(false)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    private function event(string $path): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
