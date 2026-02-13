<?php

declare(strict_types=1);

namespace App\Onboarding\Payment;

final readonly class CreateSubscriptionResult
{
    public function __construct(
        public string $gatewaySubscriptionId,
        public string $gatewayCustomerId,
        public string $approvalUrl,
    ) {
    }
}
