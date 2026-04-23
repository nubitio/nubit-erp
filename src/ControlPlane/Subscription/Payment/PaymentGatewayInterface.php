<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Payment;

use Symfony\Component\HttpFoundation\Request;

interface PaymentGatewayInterface
{
    /**
     * Identifier for this gateway (e.g. "paypal", "mercadopago").
     */
    public function getName(): string;

    /**
     * Create a subscription in the gateway and return the approval URL for redirect.
     */
    public function createSubscription(
        string $planId,
        BillingCycle $billingCycle,
        string $email,
        string $returnUrl,
        string $cancelUrl,
    ): CreateSubscriptionResult;

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(string $gatewaySubscriptionId): void;

    /**
     * Parse and validate an incoming webhook request. Returns null if signature is invalid.
     */
    public function handleWebhook(Request $request): ?PaymentEvent;

    /**
     * Query current subscription status from the gateway.
     */
    public function getSubscriptionStatus(string $gatewaySubscriptionId): SubscriptionStatus;
}
