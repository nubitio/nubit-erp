<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Context\TenantContext;
use App\Core\Platform\Tenant\TenantConnectionSwitcherInterface;
use App\Core\Platform\Tenant\TenantRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsEventListener(priority: 1024)]
readonly class TenantListener
{
    public function __construct(
        private TenantConnectionSwitcherInterface $tenantConnectionSwitcher,
        private TenantRegistryInterface $tenantManager,
        private TenantContext $tenantContext,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $this->tenantContext->setActor(null, 'http');

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $host = $request->getHost();
        $clientIp = $request->getClientIp();
        $requestId = (string)($request->headers->get('X-Request-Id') ?? '');
        if ($requestId === '') {
            $requestId = Uuid::v4()->toRfc4122();
        }
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('request_start', microtime(true));

        $this->logger->debug('TenantListener invoked', [
            'path' => $path,
            'host' => $host,
            'client_ip' => $clientIp,
            'request_id' => $requestId,
        ]);

        $excludedPaths = [
            '/admin',
            '/register',
            '/account',
            '/webhook/paypal',
            '/webhook/mercadopago',
        ];

        foreach ($excludedPaths as $excludedPath) {
            if ($path === $excludedPath || str_starts_with($path, $excludedPath . '/')) {
                $this->logger->debug('Skipping tenant switch for excluded path', ['path' => $path]);

                return;
            }
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
        $excludedSubdomains = ['localhost', 'dev', 'stg'];
        if ('' === $subdomain || in_array($subdomain, $excludedSubdomains, true)) {
            $this->logger->debug('Skipping tenant switch for excluded or empty subdomain', [
                'subdomain' => $subdomain,
                'host' => $host,
            ]);

            return;
        }

        try {
            $tenant = $this->tenantManager->getTenantByDomain($host);
            if ($tenant === null) {
                $tenant = $this->tenantManager->getTenantByName($subdomain);
            }

            $tenantName = $tenant['name'] ?? $subdomain;
            $tenantStatus = $tenant['status'] ?? 'active';

            if ($tenantStatus === 'suspended') {
                $this->logger->warning('Request to suspended tenant blocked', [
                    'tenant' => $tenantName,
                    'host' => $host,
                ]);

                throw new AccessDeniedHttpException('Service temporarily unavailable');
            }

            $request->headers->set('tenant', $tenantName);
            $this->tenantConnectionSwitcher->switchConnection($tenantName);
            $this->logger->info('Tenant connection switched', ['tenant' => $tenantName, 'host' => $host]);

            $tenantId = isset($tenant['id']) ? (int)$tenant['id'] : null;
            if ($tenantId === 0) {
                $tenantId = null;
            }

            $this->tenantContext->setTenant($tenantId, $tenantName, $host, $requestId);
            $this->setTraceAttributes($tenantId, $tenantName, $host, $requestId);
        } catch (AccessDeniedHttpException $e) {
            $event->setResponse(new JsonResponse(
                ['success' => false, 'message' => $e->getMessage()],
                Response::HTTP_FORBIDDEN,
            ));
        } catch (Throwable $throwable) {
            $this->logger->warning('Tenant resolution failed', [
                'tenant' => $subdomain,
                'host' => $host,
                'exception' => $throwable->getMessage(),
            ]);

            $event->setResponse(new JsonResponse(
                ['success' => false, 'message' => 'Tenant not found'],
                Response::HTTP_NOT_FOUND,
            ));
        }
    }

    private function setTraceAttributes(
        ?int $tenantId,
        string $tenantName,
        string $domain,
        string $requestId,
    ): void {
        if (!class_exists(\OpenTelemetry\API\Trace\Span::class)) {
            return;
        }

        $span = \OpenTelemetry\API\Trace\Span::getCurrent();
        $span->setAttribute('tenant.name', $tenantName);
        $span->setAttribute('tenant.domain', $domain);
        $span->setAttribute('request.id', $requestId);

        if ($tenantId !== null) {
            $span->setAttribute('tenant.id', $tenantId);
        }
    }
}
