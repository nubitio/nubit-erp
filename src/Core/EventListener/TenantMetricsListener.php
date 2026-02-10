<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Context\TenantContext;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
final readonly class TenantMetricsListener
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!class_exists(Globals::class)) {
            return;
        }

        $request = $event->getRequest();
        $start = $request->attributes->get('request_start');
        if (!is_float($start)) {
            return;
        }

        $durationMs = (microtime(true) - $start) * 1000;
        $statusCode = $event->getResponse()->getStatusCode();
        $route = (string)($request->attributes->get('_route') ?? '');

        $attributes = [
            'tenant.id' => $this->tenantContext->getTenantId(),
            'tenant.name' => $this->tenantContext->getTenantName(),
            'http.route' => $route,
            'http.status_code' => $statusCode,
        ];

        $meter = $this->getMeter();
        $meter->createHistogram('http.server.duration', 'ms')->record($durationMs, $attributes);
        $meter->createCounter('http.server.requests')->add(1, $attributes);
    }

    private function getMeter(): MeterInterface
    {
        $provider = Globals::meterProvider();

        return $provider->getMeter('efact');
    }
}
