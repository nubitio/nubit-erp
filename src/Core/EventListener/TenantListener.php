<?php

declare(strict_types=1);

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

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/admin/tenants')) {
            return;
        }

        $host = $request->getHost();

        // Exclude requests coming from IP addresses that are local/reserved (loopback, private ranges)
        // FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE returns false for private/reserved IPs,
        // so we invert the result to detect such addresses.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublicIp = (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if (!$isPublicIp) {
                return;
            }
        }

        $parts = explode('.', $host);
        $subdomain = $parts[0];

        // Default/known infra hosts that should not trigger tenant switching.
        $excludedSubdomains = ['localhost', 'demo', 'dev', 'test'];
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
