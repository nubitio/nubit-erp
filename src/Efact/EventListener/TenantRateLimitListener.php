<?php

declare(strict_types=1);

namespace App\Efact\EventListener;

use App\Efact\RateLimit\TenantRateLimiter;
use Nubit\Platform\Tenant\Context\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(priority: 512)]
final readonly class TenantRateLimitListener
{
    public function __construct(
        private TenantRateLimiter $rateLimiter,
        private TenantContext $tenantContext,
        private LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private string $kernelEnvironment,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if ($this->kernelEnvironment !== 'prod') {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if ($this->isNonBusinessApiRequest($path)) {
            return;
        }

        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null || $tenantName === '') {
            return;
        }

        $result = $this->rateLimiter->check($tenantName);

        $event->getRequest()->attributes->set('rate_limit_remaining', $result->remaining);

        if ($result->allowed) {
            return;
        }

        $this->logger->warning('Tenant rate limit exceeded', [
            'tenant' => $tenantName,
            'limit' => $result->limit,
            'retry_after' => $result->retryAfter,
        ]);

        $response = new JsonResponse([
            'message' => 'Too many requests',
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->set('Retry-After', (string) $result->retryAfter);
        $response->headers->set('X-RateLimit-Limit', (string) $result->limit);
        $response->headers->set('X-RateLimit-Remaining', '0');

        $event->setResponse($response);
    }

    private function isNonBusinessApiRequest(string $path): bool
    {
        return (
            $path === '/health/live'
            || $path === '/api'
            || str_starts_with($path, '/api/docs')
            || str_starts_with($path, '/api/user-notifications')
            || $path === '/api/tenant/runtime-config'
        );
    }
}
