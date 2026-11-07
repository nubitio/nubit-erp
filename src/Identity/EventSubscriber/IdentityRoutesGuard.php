<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The identity routes that must be reachable WITHOUT a session (password recovery, invitation
 * preview/accept) are opened in security.yaml unconditionally. While the module is switched off
 * the bundle's controllers cannot even be built, so an anonymous caller would get a 500.
 * Answer 404 instead, as if the feature did not exist.
 */
final readonly class IdentityRoutesGuard implements EventSubscriberInterface
{
    private const string PUBLIC_IDENTITY_PATHS = '#^/api/(auth/password/(forgot|reset)|invitations/[^/]+(/accept)?)$#';

    public function __construct(
        #[Autowire('%app.identity_enabled%')]
        private bool $identityEnabled,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Above the router (32) and the firewall (8): nothing downstream needs to run.
        return [KernelEvents::REQUEST => ['onRequest', 64]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($this->identityEnabled || !$event->isMainRequest()) {
            return;
        }
        if (preg_match(self::PUBLIC_IDENTITY_PATHS, $event->getRequest()->getPathInfo()) === 1) {
            $event->setResponse(new JsonResponse(['message' => 'No disponible.'], Response::HTTP_NOT_FOUND));
        }
    }
}
