<?php

declare(strict_types=1);

namespace App\Restaurant\Security;

use App\Settings\Capability\TenantMenuAvailabilityPolicy;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Menu filtering is only presentation. Enforce restaurant vertical and
 * entitlement availability on every restaurant API request as well.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 16)]
final readonly class RestaurantVerticalAccessListener
{
    public function __construct(
        private TenantRuntimeConfigProvider $runtimeConfig,
        private TenantMenuAvailabilityPolicy $availabilityPolicy,
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/restaurant/')) {
            return;
        }

        if (!$this->availabilityPolicy->restaurantEnabled($this->runtimeConfig->getConfig())) {
            throw new AccessDeniedHttpException('El módulo de restaurante no está habilitado para este negocio.');
        }
    }
}
