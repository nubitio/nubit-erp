<?php

namespace App\Core\EventListener;

use Throwable;
use App\Core\Service\TenantConnectionSwitcher;
use Doctrine\DBAL\Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsEventListener(priority: 1024)]
readonly class TenantListener
{
    public function __construct(
        private TenantConnectionSwitcher $tenantConnectionSwitcher,
    ) {
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/admin/tenants')) {
            return;
        }

        $host = $request->getHost();
        $parts = explode('.', $host);
        $subdomain = $parts[0] ?? '';

        // Default/known infra hosts that should not trigger tenant switching.
        $excludedSubdomains = ['localhost', 'demo'];
        if ('' === $subdomain || in_array($subdomain, $excludedSubdomains, true)) {
            return;
        }

        try {
            $request->headers->set('tenant', $subdomain);
            $this->tenantConnectionSwitcher->switchConnection($subdomain);
        } catch (Throwable $throwable) {
            throw new BadRequestHttpException(
                sprintf('Invalid tenant "%s": %s', $subdomain, $throwable->getMessage()),
                $throwable
            );
        }
    }
}
