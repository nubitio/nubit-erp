<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use Nubit\Platform\Tenant\Context\TenantContext;
use App\Core\RateLimit\TenantRateLimiter;
use Psr\Log\LoggerInterface;
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
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
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

        $response->headers->set('Retry-After', (string)$result->retryAfter);
        $response->headers->set('X-RateLimit-Limit', (string)$result->limit);
        $response->headers->set('X-RateLimit-Remaining', '0');

        $event->setResponse($response);
    }
}
