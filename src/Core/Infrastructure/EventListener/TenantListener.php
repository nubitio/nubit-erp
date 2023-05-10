<?php

namespace App\Core\Infrastructure\EventListener;

use App\Core\Infrastructure\Doctrine\DBAL\TenantConnection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(priority: 1024)]
readonly class TenantListener
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry
    )
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $host = $event->getRequest()->getHost();
        $subdomain = explode('.', $host)[0];
        $excludedSubdomains = ['localhost', 'www', 'demo', 'api', 'app', 'admin'];
        if ($subdomain && !in_array($subdomain, $excludedSubdomains)) {
            $event->getRequest()->headers->set('tenant', $subdomain);
            $this->switchConnection($subdomain);
        }
    }

    private function switchConnection(string $tenant): void
    {
        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection();
        $connection->switchTenant($tenant);
    }
}