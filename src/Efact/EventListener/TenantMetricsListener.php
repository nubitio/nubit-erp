<?php

declare(strict_types=1);

namespace App\Efact\EventListener;

use Nubit\Platform\Tenant\Context\TenantContext;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
final class TenantMetricsListener
{
    private ?HistogramInterface $requestDurationHistogram = null;
    private ?CounterInterface $requestCounter = null;

    public function __construct(
        private TenantContext $tenantContext,
    ) {}

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
        $route = (string) ($request->attributes->get('_route') ?? '');

        $attributes = [
            'tenant.id' => $this->tenantContext->getTenantId(),
            'tenant.name' => $this->tenantContext->getTenantName(),
            'http.route' => $route,
            'http.status_code' => $statusCode,
        ];

        $this->getRequestDurationHistogram()->record($durationMs, $attributes);
        $this->getRequestCounter()->add(1, $attributes);
    }

    private function getMeter(): MeterInterface
    {
        $provider = Globals::meterProvider();

        return $provider->getMeter('efact');
    }

    private function getRequestDurationHistogram(): HistogramInterface
    {
        if ($this->requestDurationHistogram === null) {
            $this->requestDurationHistogram = $this->getMeter()->createHistogram('http.server.duration', 'ms');
        }

        return $this->requestDurationHistogram;
    }

    private function getRequestCounter(): CounterInterface
    {
        if ($this->requestCounter === null) {
            $this->requestCounter = $this->getMeter()->createCounter('http.server.requests');
        }

        return $this->requestCounter;
    }
}
