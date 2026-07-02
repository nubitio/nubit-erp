<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Payment;

final readonly class PaymentEvent
{
    public function __construct(
        public string $gateway,
        public string $eventType,
        public string $gatewayEventId,
        public string $gatewaySubscriptionId,
        /** @var array<string, mixed> */
        public array $payload,
    ) {}
}
