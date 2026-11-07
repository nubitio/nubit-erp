<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Activity\UserAgentSummary;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Nubit\AdminBundle\Identity\TotpManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** Stamps usuario.last_login_at on every successful sign-in (not on token refresh). */
final readonly class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Connection $connection,
        private ActivityRecorderInterface $activity,
        // Only a service while the identity module is on.
        private ?TotpManager $totp = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ('nubit_admin_auth_login' !== $event->getRequest()->attributes->get('_route')) {
            return;
        }
        $user = $event->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        // Plain SQL: an ORM flush here would run inside the authentication event.
        $this->connection->executeStatement('UPDATE usuario SET last_login_at = :now WHERE usuario_id = :id', [
            'now' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
            'id' => $user->getId(),
        ]);

        $detail = implode(' · ', array_filter([
            UserAgentSummary::describe($event->getRequest()->headers->get('User-Agent')),
            $this->totp?->isEnrolled($user->getUserIdentifier()) === true ? 'con verificación en dos pasos' : null,
        ]));
        $this->activity->record(
            ActivityType::LOGIN,
            sprintf('%s inició sesión', $user->getUsername()),
            $detail === '' ? null : $detail,
            actor: $user,
        );
    }
}
