<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Efact\Audit\AuditLogger;
use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use Nubit\AdminBundle\Identity\Exception\TotpRequiredException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Audit trail for failed logins. Replaces the AuditLogger call that lived
 * inside the legacy JWTAuthenticator — the bundle authenticator dispatches
 * the standard Symfony security events instead.
 */
final readonly class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ActivityRecorderInterface $activity,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginFailureEvent::class => 'onLoginFailure'];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ('nubit_admin_auth_login' !== $request->attributes->get('_route')) {
            return;
        }

        $username = '';
        $data = json_decode($request->getContent(), true);
        if (\is_array($data)) {
            $username = (string) ($data['username'] ?? '');
        }

        $this->auditLogger->log('auth.login_failed', [
            'username' => $username,
            'route' => 'nubit_admin_auth_login',
            'ip' => $request->getClientIp() ?? 'unknown',
            'error' => $event->getException()->getMessage(),
        ]);

        // "Second factor required" is the normal first step of a 2FA sign-in, not a failure.
        if ($event->getException() instanceof TotpRequiredException) {
            return;
        }
        $this->activity->record(
            ActivityType::LOGIN_FAILED,
            $username === '' ? 'Intento de ingreso fallido' : sprintf('Intento de ingreso fallido de «%s»', $username),
            subject: $username === '' ? null : $username,
        );
    }
}
