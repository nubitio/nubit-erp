<?php

declare(strict_types=1);

namespace App\Tests\Identity\EventSubscriber;

use App\Efact\Audit\AuditLogger;
use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\EventSubscriber\LoginAuditSubscriber;
use Nubit\AdminBundle\Identity\Exception\TotpRequiredException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

final class LoginAuditSubscriberTest extends TestCase
{
    public function testAWrongPasswordIsRecordedWithTheTypedUsername(): void
    {
        $activity = $this->createMock(ActivityRecorderInterface::class);
        $activity
            ->expects(self::once())
            ->method('record')
            ->with('auth.login_failed', 'Intento de ingreso fallido de «ana»', null, 'ana');

        new LoginAuditSubscriber($this->createStub(AuditLogger::class), $activity)->onLoginFailure($this->event(
            new AuthenticationException('Invalid credentials'),
            'ana',
        ));
    }

    public function testTheNormalSecondFactorStepIsNotAFailedSignIn(): void
    {
        $activity = $this->createMock(ActivityRecorderInterface::class);
        $activity->expects(self::never())->method('record');

        new LoginAuditSubscriber($this->createStub(AuditLogger::class), $activity)->onLoginFailure($this->event(
            new TotpRequiredException(),
            'ana',
        ));
    }

    public function testOnlyTheLoginRouteCounts(): void
    {
        $activity = $this->createMock(ActivityRecorderInterface::class);
        $activity->expects(self::never())->method('record');

        new LoginAuditSubscriber($this->createStub(AuditLogger::class), $activity)->onLoginFailure($this->event(
            new AuthenticationException('x'),
            'ana',
            'some_other_route',
        ));
    }

    private function event(
        AuthenticationException $exception,
        string $username,
        string $route = 'nubit_admin_auth_login',
    ): LoginFailureEvent {
        $request = Request::create('/api/auth/login', 'POST', content: json_encode([
            'username' => $username,
            'password' => bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR));
        $request->attributes->set('_route', $route);

        return new LoginFailureEvent(
            $exception,
            $this->createStub(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );
    }
}
