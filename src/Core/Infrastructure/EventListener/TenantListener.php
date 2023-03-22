<?php

namespace App\Core\Infrastructure\EventListener;

use App\Core\Infrastructure\Doctrine\DBAL\TenantConnection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener]
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
        $excludedSubdomains = ['localhost', 'www', 'demo', 'api', 'admin'];
        if ($subdomain && !in_array($subdomain, $excludedSubdomains)) {
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