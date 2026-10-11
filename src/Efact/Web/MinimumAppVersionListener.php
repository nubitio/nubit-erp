<?php

declare(strict_types=1);

namespace App\Efact\Web;

use App\Efact\Service\AppReleaseInfo;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class MinimumAppVersionListener
{
    public function __construct(
        private AppReleaseInfo $release,
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (
            !str_starts_with($path, '/api/')
            || str_starts_with($path, '/api/auth/')
            || str_starts_with($path, '/api/test/')
            || str_starts_with($path, '/api/control-plane/')
            || str_starts_with($path, '/api/account/')
            || !in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], strict: true)
        ) {
            return;
        }

        // Explicitly versioned clients and legacy cookie/CSRF browser clients
        // participate. Header-less machine integrations retain their contract.
        $version =
            $request->headers->get('X-App-Contract-Version') ?? ($request->headers->has('X-CSRF-Token') ? '1' : null);
        if ($version === null) {
            return;
        }
        if ((int) $version >= $this->release->get()['minimumOfflineContractVersion']) {
            return;
        }

        $event->setController(
            static fn(): JsonResponse => new JsonResponse(
                [
                    'message' => 'Actualiza la aplicación para continuar. Las ventas pendientes se conservarán.',
                    'errorCode' => 'APP_UPDATE_REQUIRED',
                ],
                status: 426,
                headers: ['Cache-Control' => 'no-store'],
            ),
        );
    }
}
