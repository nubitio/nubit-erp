<?php

declare(strict_types=1);

namespace App\Onboarding\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

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
        $token = $this->getEnv('MERCADOPAGO_ACCESS_TOKEN');

        $ch = curl_init(self::API_BASE . $path);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for MercadoPago API.');
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!\is_string($result)) {
            throw new \RuntimeException('MercadoPago API request failed.');
        }

        if ($httpCode === 204) {
            return [];
        }

        $decoded = json_decode($result, true);
        if (!\is_array($decoded)) {
            throw new \RuntimeException('MercadoPago API returned invalid JSON. HTTP ' . $httpCode);
        }

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

    private function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->getEnv('MERCADOPAGO_WEBHOOK_SECRET');

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

        $dataId = (string) $request->query->get('data.id', '');
        $manifest = 'id:' . $dataId . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
        $computed = hash_hmac('sha256', $manifest, $secret);

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
     * Resolves MercadoPago pricing from env config.
     *
     * MERCADOPAGO_PLAN_MAP format:
     * {"basic_monthly":{"amount":29.90,"currency":"PEN"},"basic_annual":{"amount":299,"currency":"PEN"},
     *  "pro_monthly":{"amount":79.90,"currency":"PEN"},"pro_annual":{"amount":799,"currency":"PEN"}}
     *
     * @return array{amount:float,currency:string}
     */
    private function resolvePlanConfig(string $plan, BillingCycle $billingCycle): array
    {
        $mapJson = (string) (
            $_ENV['MERCADOPAGO_PLAN_MAP']
            ?? $_SERVER['MERCADOPAGO_PLAN_MAP']
            ?? getenv('MERCADOPAGO_PLAN_MAP')
            ?: '{}'
        );

        $key = $plan . '_' . $billingCycle->value;

        /** @var array<string, array{amount:float,currency:string}> $map */
        $map = json_decode($mapJson, true) ?: [];
        $config = $map[$key] ?? null;

        if ($config === null) {
            throw new \InvalidArgumentException('No MercadoPago pricing configured for: ' . $key);
        }

        return $config;
    }

    private function getEnv(string $name): string
    {
        $value = trim((string) (
            $_ENV[$name]
            ?? $_SERVER[$name]
            ?? getenv($name)
            ?: ''
        ));

        if ($value === '') {
            throw new \RuntimeException('Missing environment variable: ' . $name);
        }

        return $value;
    }
}
