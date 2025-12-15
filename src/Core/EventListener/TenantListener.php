<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Service\TenantConnectionSwitcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

#[AsEventListener(priority: 1024)]
readonly class TenantListener
{
    public function __construct(
        private TenantConnectionSwitcher $tenantConnectionSwitcher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $host = $request->getHost();
        $clientIp = $request->getClientIp();

        $this->logger->debug('TenantListener invoked', [
            'path' => $path,
            'host' => $host,
            'client_ip' => $clientIp,
        ]);

        if (str_starts_with($path, '/admin/tenants')) {
            $this->logger->debug('Skipping tenant switch for admin tenant management path', ['path' => $path]);

            return;
        }

        // Exclude requests coming from IP addresses that are local/reserved (loopback, private ranges)
        // FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE returns false for private/reserved IPs,
        // so we invert the result to detect such addresses.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublicIp = (bool)filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if (!$isPublicIp) {
                $this->logger->debug('Host is private or reserved IP; skipping tenant switching', ['host' => $host]);

                return;
            }
        }

        $parts = explode('.', $host);
        $subdomain = $parts[0];

        // Default/known infra hosts that should not trigger tenant switching.
        $excludedSubdomains = ['localhost', 'demo', 'dev', 'test'];
        if ('' === $subdomain || in_array($subdomain, $excludedSubdomains, true)) {
            $this->logger->debug('Skipping tenant switch for excluded or empty subdomain', [
                'subdomain' => $subdomain,
                'host' => $host,
            ]);

            return;
        }

        try {
            $request->headers->set('tenant', $subdomain);
            $this->tenantConnectionSwitcher->switchConnection($subdomain);
            $this->logger->info('Tenant connection switched', ['tenant' => $subdomain, 'host' => $host]);
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf('Invalid tenant "%s": %s', $subdomain, $throwable->getMessage()), [
                'tenant' => $subdomain,
                'exception' => $throwable,
            ]);

            throw new BadRequestHttpException(
                sprintf('Invalid tenant "%s": %s', $subdomain, $throwable->getMessage()),
                $throwable
            );
        }
    }
}
