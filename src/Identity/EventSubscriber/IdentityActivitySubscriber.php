<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records the identity actions that happen inside nubit/admin-bundle's own controllers, which have
 * no event to hook: a second factor being confirmed or removed by its owner. Only successful
 * responses count, so a wrong code leaves no "enabled" entry.
 */
final readonly class IdentityActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityRecorderInterface $activity,
        private Security $security,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        // route => [status that means success, event type, what the user did]
        $spec = match ($event->getRequest()->attributes->get('_route')) {
            'nubit_admin_totp_confirm' => [200, ActivityType::TOTP_ENABLED, 'activó la verificación en dos pasos'],
            'nubit_admin_totp_disable' => [204, ActivityType::TOTP_DISABLED, 'desactivó la verificación en dos pasos'],
            default => null,
        };
        if ($spec === null) {
            return;
        }
        [$status, $type, $phrase] = $spec;
        if ($event->getResponse()->getStatusCode() !== $status) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->activity->record($type, sprintf('%s %s', $user->getUsername(), $phrase), actor: $user);
    }
}
