<?php

namespace App\Core\Infrastructure\EventListener;

use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(priority: 1024)]
readonly class TenantListener
{
    public function __construct(
        private TenantConnectionSwitcher $tenantConnectionSwitcher,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $host = $event->getRequest()->getHost();
        $subdomain = explode('.', $host)[0];
        $excludedSubdomains = ['localhost', 'www', 'demo', 'api', 'app', 'admin'];
        if ($subdomain && !in_array($subdomain, $excludedSubdomains)) {
            $event->getRequest()->headers->set('tenant', $subdomain);
            $this->tenantConnectionSwitcher->switchConnection($subdomain);
        }
    }
}
