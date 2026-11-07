<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** Stamps usuario.last_login_at on every successful sign-in (not on token refresh). */
final readonly class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Connection $connection,
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
    }
}
