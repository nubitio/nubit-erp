<?php

namespace App\Core\Domain\Messenger;

use App\Core\Infrastructure\Doctrine\DBAL\TenantConnection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

readonly class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private ConnectionRegistry $connectionRegistry
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $cli = 'cli' === php_sapi_name();

        if (!$cli) {
            $tenant = $this->requestStack->getCurrentRequest()?->headers->get('tenant');

            if (null === $tenant) {
                throw new \RuntimeException('Tenant not found');
            }

            if (null === $envelope->last(TenantStamp::class)) {
                $envelope = $envelope->with(new TenantStamp($tenant));
            }
        }

        $tenant = $envelope->last(TenantStamp::class);

        if (null === $tenant) {
            throw new \RuntimeException('Tenant not found');
        }

        dump($tenant);

        $this->switchConnection($tenant->tenant);

        return $stack->next()->handle($envelope, $stack);
    }

    private function switchConnection(string $tenant): void
    {
        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection();
        $connection->switchTenant($tenant);
    }
}
