<?php

declare(strict_types=1);

namespace App\Onboarding\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PayPal Subscriptions API v1 integration.
 *
 * Requires env vars: PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_MODE (sandbox|live).
 * Plan IDs are configured in PAYPAL_PLAN_MAP as JSON: {"basic":"P-xxx","pro":"P-yyy"}.
 */
final class PayPalGateway implements PaymentGatewayInterface
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function createSubscription(
        string $planId,
        BillingCycle $billingCycle,
        string $email,
        string $returnUrl,
        string $cancelUrl,
    ): CreateSubscriptionResult {
        $paypalPlanId = $this->resolvePayPalPlanId($planId, $billingCycle);

        $body = [
            'plan_id' => $paypalPlanId,
            'subscriber' => [
                'email_address' => $email,
            ],
            'application_context' => [
                'brand_name' => 'eFact',
                'locale' => 'es-PE',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $response = $this->apiRequest('POST', '/v1/billing/subscriptions', $body);

        $approvalUrl = '';
        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approvalUrl = (string) $link['href'];
                break;
            }
        }

        if ($approvalUrl === '') {
            throw new \RuntimeException('PayPal did not return an approval URL.');
        }

        return new CreateSubscriptionResult(
            gatewaySubscriptionId: (string) $response['id'],
            gatewayCustomerId: '',
            approvalUrl: $approvalUrl,
        );
    }

    public function cancelSubscription(string $gatewaySubscriptionId): void
    {
        $this->apiRequest('POST', '/v1/billing/subscriptions/' . $gatewaySubscriptionId . '/cancel', [
            'reason' => 'Cancelled by owner',
        ]);
    }

    public function handleWebhook(Request $request): ?PaymentEvent
    {
        if (!$this->verifyWebhookSignature($request)) {
            $this->logger->warning('PayPal webhook signature verification failed');

            return null;
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return null;
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $eventId = (string) ($payload['id'] ?? '');
        $resource = $payload['resource'] ?? [];
        $subscriptionId = (string) ($resource['id'] ?? $resource['billing_agreement_id'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return null;
        }

        return new PaymentEvent(
            gateway: 'paypal',
            eventType: $eventType,
            gatewayEventId: $eventId,
            gatewaySubscriptionId: $subscriptionId,
            payload: $payload,
        );
    }

    public function getSubscriptionStatus(string $gatewaySubscriptionId): SubscriptionStatus
    {
        $response = $this->apiRequest('GET', '/v1/billing/subscriptions/' . $gatewaySubscriptionId);
        $status = strtoupper((string) ($response['status'] ?? ''));

        return match ($status) {
            'APPROVAL_PENDING' => SubscriptionStatus::Pending,
            'APPROVED', 'ACTIVE' => SubscriptionStatus::Active,
            'SUSPENDED' => SubscriptionStatus::Suspended,
            'CANCELLED', 'EXPIRED' => SubscriptionStatus::Cancelled,
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
        $baseUrl = $this->getBaseUrl();
        $token = $this->getAccessToken();

        $ch = curl_init($baseUrl . $path);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for PayPal API.');
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
            throw new \RuntimeException('PayPal API request failed.');
        }

        if ($httpCode === 204) {
            return [];
        }

        $decoded = json_decode($result, true);
        if (!\is_array($decoded)) {
            throw new \RuntimeException('PayPal API returned invalid JSON. HTTP ' . $httpCode);
        }

        if ($httpCode >= 400) {
            $this->logger->error('PayPal API error', [
                'http_code' => $httpCode,
                'path' => $path,
                'response' => $decoded,
            ]);

            throw new \RuntimeException('PayPal API error: ' . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $clientId = $this->getEnv('PAYPAL_CLIENT_ID');
        $clientSecret = $this->getEnv('PAYPAL_CLIENT_SECRET');
        $baseUrl = $this->getBaseUrl();

        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for PayPal auth.');
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!\is_string($result) || $httpCode >= 400) {
            throw new \RuntimeException('PayPal OAuth2 token request failed. HTTP ' . $httpCode);
        }

        $decoded = json_decode($result, true);
        $token = (string) ($decoded['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('PayPal OAuth2 returned no access_token.');
        }

        $this->accessToken = $token;

        return $token;
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = $this->getEnv('PAYPAL_WEBHOOK_ID');
        $body = [
            'auth_algo' => $request->headers->get('PAYPAL-AUTH-ALGO', ''),
            'cert_url' => $request->headers->get('PAYPAL-CERT-URL', ''),
            'transmission_id' => $request->headers->get('PAYPAL-TRANSMISSION-ID', ''),
            'transmission_sig' => $request->headers->get('PAYPAL-TRANSMISSION-SIG', ''),
            'transmission_time' => $request->headers->get('PAYPAL-TRANSMISSION-TIME', ''),
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($request->getContent(), true),
        ];

        try {
            $response = $this->apiRequest('POST', '/v1/notifications/verify-webhook-signature', $body);

            return ($response['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            $this->logger->error('PayPal webhook signature verification request failed', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Resolves PayPal plan ID from env config.
     *
     * PAYPAL_PLAN_MAP format:
     * {"basic_monthly":"P-xxx","basic_annual":"P-yyy","pro_monthly":"P-zzz","pro_annual":"P-www"}
     */
    private function resolvePayPalPlanId(string $plan, BillingCycle $billingCycle): string
    {
        $mapJson = (string) (
            $_ENV['PAYPAL_PLAN_MAP']
            ?? $_SERVER['PAYPAL_PLAN_MAP']
            ?? getenv('PAYPAL_PLAN_MAP')
            ?: '{}'
        );

        /** @var array<string, string> $map */
        $map = json_decode($mapJson, true) ?: [];
        $key = $plan . '_' . $billingCycle->value;
        $paypalId = $map[$key] ?? '';
        if ($paypalId === '') {
            throw new \InvalidArgumentException('No PayPal plan ID configured for: ' . $key);
        }

        return $paypalId;
    }

    private function getBaseUrl(): string
    {
        $mode = (string) (
            $_ENV['PAYPAL_MODE']
            ?? $_SERVER['PAYPAL_MODE']
            ?? getenv('PAYPAL_MODE')
            ?: 'sandbox'
        );

        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
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
