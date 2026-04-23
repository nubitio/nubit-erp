<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MercadoPago Preapproval (subscriptions) API integration.
 *
 * Requires env vars: MERCADOPAGO_ACCESS_TOKEN, MERCADOPAGO_WEBHOOK_SECRET.
 * Plan pricing is configured in MERCADOPAGO_PLAN_MAP as JSON:
 * {"basic":{"amount":29.90,"currency":"PEN"},"pro":{"amount":79.90,"currency":"PEN"}}
 */
final class MercadoPagoGateway implements PaymentGatewayInterface
{
    private const string API_BASE = 'https://api.mercadopago.com';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'MERCADOPAGO_ACCESS_TOKEN')]
        private readonly string $accessToken,
        #[Autowire(env: 'MERCADOPAGO_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
        #[Autowire(env: 'MERCADOPAGO_PLAN_MAP')]
        private readonly string $planMapJson,
    ) {
    }

    public function getName(): string
    {
        return 'mercadopago';
    }

    public function createSubscription(
        string $planId,
        BillingCycle $billingCycle,
        string $email,
        string $returnUrl,
        string $cancelUrl,
    ): CreateSubscriptionResult {
        $planConfig = $this->resolvePlanConfig($planId, $billingCycle);

        $frequency = $billingCycle === BillingCycle::Annual ? 12 : 1;
        $cycleLabel = $billingCycle === BillingCycle::Annual ? 'Anual' : 'Mensual';

        $body = [
            'reason' => 'eFact - Plan ' . ucfirst($planId) . ' ' . $cycleLabel,
            'auto_recurring' => [
                'frequency' => $frequency,
                'frequency_type' => 'months',
                'transaction_amount' => $planConfig['amount'],
                'currency_id' => $planConfig['currency'],
            ],
            'payer_email' => $email,
            'back_url' => $returnUrl,
            'status' => 'pending',
        ];

        $response = $this->apiRequest('POST', '/preapproval', $body);

        $approvalUrl = (string) ($response['init_point'] ?? '');
        if ($approvalUrl === '') {
            throw new \RuntimeException('MercadoPago did not return an init_point URL.');
        }

        return new CreateSubscriptionResult(
            gatewaySubscriptionId: (string) $response['id'],
            gatewayCustomerId: (string) ($response['payer_id'] ?? ''),
            approvalUrl: $approvalUrl,
        );
    }

    public function cancelSubscription(string $gatewaySubscriptionId): void
    {
        $this->apiRequest('PUT', '/preapproval/' . $gatewaySubscriptionId, [
            'status' => 'cancelled',
        ]);
    }

    public function handleWebhook(Request $request): ?PaymentEvent
    {
        if (!$this->verifyWebhookSignature($request)) {
            $this->logger->warning('MercadoPago webhook signature verification failed');

            return null;
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return null;
        }

        $type = (string) ($payload['type'] ?? '');
        $action = (string) ($payload['action'] ?? '');
        $dataId = (string) ($payload['data']['id'] ?? '');
        $eventId = (string) ($payload['id'] ?? $dataId);

        if ($eventId === '' || $type === '') {
            return null;
        }

        $eventType = $type;
        if ($action !== '') {
            $eventType = $type . '.' . $action;
        }

        $subscriptionId = '';
        if ($type === 'subscription_preapproval') {
            $subscriptionId = $dataId;
        } elseif ($type === 'payment') {
            $subscriptionId = $this->resolveSubscriptionFromPayment($dataId);
        }

        return new PaymentEvent(
            gateway: 'mercadopago',
            eventType: $eventType,
            gatewayEventId: (string) ($payload['id'] ?? $eventId),
            gatewaySubscriptionId: $subscriptionId,
            payload: $payload,
        );
    }

    public function getSubscriptionStatus(string $gatewaySubscriptionId): SubscriptionStatus
    {
        $response = $this->apiRequest('GET', '/preapproval/' . $gatewaySubscriptionId);
        $status = mb_strtolower((string) ($response['status'] ?? ''));

        return match ($status) {
            'pending' => SubscriptionStatus::Pending,
            'authorized', 'active' => SubscriptionStatus::Active,
            'paused' => SubscriptionStatus::Suspended,
            'cancelled' => SubscriptionStatus::Cancelled,
            default => SubscriptionStatus::Pending,
        };
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function apiRequest(string $method, string $path, ?array $body = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, self::API_BASE . $path, $options);

        $httpCode = $response->getStatusCode();

        if ($httpCode === 204) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $response->toArray(false);

        if ($httpCode >= 400) {
            $this->logger->error('MercadoPago API error', [
                'http_code' => $httpCode,
                'path' => $path,
                'response' => $decoded,
            ]);

            throw new \RuntimeException('MercadoPago API error: ' . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    private function verifyWebhookSignature(Request $request): ?bool
    {
        $xSignature = $request->headers->get('x-signature', '');
        $xRequestId = $request->headers->get('x-request-id', '');

        if ($xSignature === '' || $xRequestId === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $xSignature) as $part) {
            $kv = explode('=', trim($part), 2);
            if (\count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $ts = $parts['ts'] ?? '';
        $hash = $parts['v1'] ?? '';

        if ($ts === '' || $hash === '') {
            return false;
        }

        // Replay-protection: reject webhooks older than 300 seconds or more than 60 seconds in the future
        $now = time();
        $ts = (int) $ts;
        if ($ts < ($now - 300) || $ts > ($now + 60)) {
            $this->logger->warning('MercadoPago webhook rejected: timestamp out of window', [
                'ts' => $ts,
                'now' => $now,
                'drift_seconds' => $now - $ts,
            ]);

            return null;
        }

        $dataId = (string) $request->query->get('data.id', '');
        $manifest = 'id:' . $dataId . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
        $computed = hash_hmac('sha256', $manifest, $this->webhookSecret);

        return hash_equals($computed, $hash);
    }

    private function resolveSubscriptionFromPayment(string $paymentId): string
    {
        if ($paymentId === '') {
            return '';
        }

        try {
            $payment = $this->apiRequest('GET', '/v1/payments/' . $paymentId);

            return (string) ($payment['metadata']['preapproval_id'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Resolves MercadoPago pricing from injected plan map config.
     *
     * MERCADOPAGO_PLAN_MAP format:
     * {"basic_monthly":{"amount":29.90,"currency":"PEN"},"basic_annual":{"amount":299,"currency":"PEN"},
     *  "pro_monthly":{"amount":79.90,"currency":"PEN"},"pro_annual":{"amount":799,"currency":"PEN"}}
     *
     * @return array{amount:float,currency:string}
     */
    private function resolvePlanConfig(string $plan, BillingCycle $billingCycle): array
    {
        $key = $plan . '_' . $billingCycle->value;

        /** @var array<string, array{amount:float,currency:string}> $map */
        $map = json_decode($this->planMapJson, true) ?: [];
        $config = $map[$key] ?? null;

        if ($config === null) {
            throw new \InvalidArgumentException('No MercadoPago pricing configured for: ' . $key);
        }

        return $config;
    }
}
