<?php

declare(strict_types=1);

namespace App\Efact\EventListener;

use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private TenantRegistryInterface $tenantRegistry,
        private TenantContext $tenantContext,
        private LoggerInterface $logger,
        #[Autowire(param: 'kernel.environment')]
        private string $kernelEnvironment = 'prod',
        private string $localTenantName = 'demo',
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $this->tenantContext->setActor(null, 'http');

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $host = $request->getHost();
        $clientIp = $request->getClientIp();
        $requestId = (string) ($request->headers->get('X-Request-Id') ?? '');
        if ($requestId === '') {
            $requestId = Uuid::v4()->toRfc4122();
        }
        $request->attributes->set('request_id', $requestId);

        $this->logger->debug('TenantListener invoked', [
            'path' => $path,
            'host' => $host,
            'client_ip' => $clientIp,
            'request_id' => $requestId,
        ]);

        $excludedPaths = [
            '/admin',
            '/api/account',
            '/register',
            '/account',
            '/webhook/paypal',
            '/webhook/mercadopago',
            // Docker/K8s probes hit localhost before any tenant exists.
            '/health',
            // The integration API resolves its tenant from the API key, not the
            // host — ApiKeyAuthenticator switches the connection itself.
            '/api/v1',
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
            $isPublicIp = (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if (!$isPublicIp) {
                if (in_array($host, ['127.0.0.1', '::1'], true)) {
                    $this->activateLocalTenant($event, $host, $requestId);

                    return;
                }

                // Docker resolves the CI service hostname to a private
                // container IP. Only the explicitly isolated e2e/test
                // environments may use that address for the local tenant;
                // production keeps private-IP tenant switching disabled.
                if (in_array($this->kernelEnvironment, ['e2e', 'test'], true)) {
                    $this->activateLocalTenant($event, $host, $requestId);

                    return;
                }

                $this->logger->debug('Host is private or reserved IP; skipping tenant switching', ['host' => $host]);

                return;
            }
        }

        $parts = explode('.', $host);
        $subdomain = $parts[0];

        // Default/known infra hosts that should not trigger tenant switching.
        // 'host' covers host.docker.internal used in Docker Desktop environments.
        $excludedSubdomains = ['localhost', 'dev', 'stg', 'host'];
        if ('' === $subdomain || in_array($subdomain, $excludedSubdomains, strict: true)) {
            if ($subdomain === 'localhost' || $subdomain === 'host') {
                $this->activateLocalTenant($event, $host, $requestId);

                return;
            }
            $this->logger->debug('Skipping tenant switch for excluded or empty subdomain', [
                'subdomain' => $subdomain,
                'host' => $host,
            ]);

            return;
        }

        try {
            $tenant = $this->tenantRegistry->getTenantByDomain($host);
            if ($tenant === null) {
                $tenant = $this->tenantRegistry->getTenantByName($subdomain);
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

            $tenantId = isset($tenant['id']) ? (int) $tenant['id'] : null;
            if ($tenantId === 0) {
                $tenantId = null;
            }

            $this->tenantContext->setTenant($tenantId, $tenantName, $host, $requestId);
            $this->setTraceAttributes($tenantId, $tenantName, $host, $requestId);
        } catch (AccessDeniedHttpException $e) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN));
        } catch (Throwable $throwable) {
            $this->logger->warning('Tenant resolution failed', [
                'tenant' => $subdomain,
                'host' => $host,
                'exception' => $throwable->getMessage(),
            ]);

            $event->setResponse(new JsonResponse([
                'success' => false,
                'message' => 'Tenant not found',
            ], Response::HTTP_NOT_FOUND));
        }
    }

    private function activateLocalTenant(RequestEvent $event, string $host, string $requestId): void
    {
        try {
            $tenant = $this->tenantRegistry->getTenantByName($this->localTenantName);
            if ($tenant === null) {
                throw new \RuntimeException('Local tenant not found');
            }

            $tenantName = (string) ($tenant['name'] ?? $this->localTenantName);
            if (($tenant['status'] ?? 'active') === 'suspended') {
                throw new AccessDeniedHttpException('Service temporarily unavailable');
            }

            $tenantId = isset($tenant['id']) ? (int) $tenant['id'] : null;
            $this->tenantContext->setTenant($tenantId ?: null, $tenantName, $host, $requestId);
            $this->setTraceAttributes($tenantId ?: null, $tenantName, $host, $requestId);
        } catch (AccessDeniedHttpException $exception) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_FORBIDDEN));
        } catch (Throwable $throwable) {
            $this->logger->warning('Local tenant resolution failed', [
                'host' => $host,
                'exception' => $throwable->getMessage(),
            ]);
            $event->setResponse(new JsonResponse([
                'success' => false,
                'message' => 'Tenant not found',
            ], Response::HTTP_NOT_FOUND));
        }
    }

    private function setTraceAttributes(?int $tenantId, string $tenantName, string $domain, string $requestId): void
    {
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
