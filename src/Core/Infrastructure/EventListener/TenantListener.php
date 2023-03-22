<?php

namespace App\Core\Infrastructure\EventListener;

use App\Core\Infrastructure\Doctrine\DBAL\TenantConnection;
use Doctrine\DBAL\Exception;
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

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function __invoke(RequestEvent $event): void
    {
        $host = $event->getRequest()->getHost();
        $subdomain = explode('.', $host)[0];
        if ($subdomain !== 'localhost' && $subdomain !== 'www') {
            $this->switchConnection($subdomain);
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    private function switchConnection(string $tenant): void
    {
        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection();
        $connection->switchTenant($tenant);
    }
}