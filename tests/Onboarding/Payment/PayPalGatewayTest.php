<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Payment\PayPalGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class PayPalGatewayTest extends TestCase
{
    private function buildGateway(
        string $clientId = 'test-client-id',
        string $clientSecret = 'test-client-secret',
        string $webhookId = 'test-webhook-id',
        string $mode = 'sandbox',
        string $planMapJson = '{}',
        MockHttpClient|null $httpClient = null,
    ): PayPalGateway {
        return new PayPalGateway(
            logger: $this->createMock(LoggerInterface::class),
            httpClient: $httpClient ?? new MockHttpClient(),
            clientId: $clientId,
            clientSecret: $clientSecret,
            webhookId: $webhookId,
            mode: $mode,
            planMapJson: $planMapJson,
        );
    }

    public function testGetName(): void
    {
        $gateway = $this->buildGateway();

        self::assertSame('paypal', $gateway->getName());
    }

    public function testGetBaseUrlForLiveMode(): void
    {
        $gateway = $this->buildGateway(mode: 'live');

        self::assertSame('https://api-m.paypal.com', $this->invokePrivate($gateway, 'getBaseUrl'));
    }

    public function testGetBaseUrlForSandboxMode(): void
    {
        $gateway = $this->buildGateway(mode: 'sandbox');

        self::assertSame('https://api-m.sandbox.paypal.com', $this->invokePrivate($gateway, 'getBaseUrl'));
    }

    public function testGetBaseUrlDefaultsToSandboxForUnknownMode(): void
    {
        $gateway = $this->buildGateway(mode: 'unknown');

        self::assertSame('https://api-m.sandbox.paypal.com', $this->invokePrivate($gateway, 'getBaseUrl'));
    }

    public function testResolvePayPalPlanIdResolvesKnownKey(): void
    {
        $gateway = $this->buildGateway(planMapJson: '{"basic_monthly":"P-123","pro_annual":"P-999"}');

        $planId = $this->invokePrivate($gateway, 'resolvePayPalPlanId', ['basic', BillingCycle::Monthly]);
        self::assertSame('P-123', $planId);

        $planId = $this->invokePrivate($gateway, 'resolvePayPalPlanId', ['pro', BillingCycle::Annual]);
        self::assertSame('P-999', $planId);
    }

    public function testResolvePayPalPlanIdThrowsForMissingKey(): void
    {
        $gateway = $this->buildGateway(planMapJson: '{"basic_monthly":"P-123"}');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No PayPal plan ID configured for: pro_annual');
        $this->invokePrivate($gateway, 'resolvePayPalPlanId', ['pro', BillingCycle::Annual]);
    }

    public function testCredentialsAreStoredViaConstructorInjection(): void
    {
        $gateway = $this->buildGateway(
            clientId: 'my-client-id',
            clientSecret: 'my-secret',
            webhookId: 'my-webhook-id',
        );

        // Verify credentials are accessible as properties (no env reads)
        $reflection = new \ReflectionClass($gateway);

        $clientIdProp = $reflection->getProperty('clientId');
        self::assertSame('my-client-id', $clientIdProp->getValue($gateway));

        $clientSecretProp = $reflection->getProperty('clientSecret');
        self::assertSame('my-secret', $clientSecretProp->getValue($gateway));

        $webhookIdProp = $reflection->getProperty('webhookId');
        self::assertSame('my-webhook-id', $webhookIdProp->getValue($gateway));
    }

    /**
     * Happy-path webhook verification: mocked OAuth token + mocked verify-webhook-signature → SUCCESS.
     * Expects a fully-populated PaymentEvent — zero real HTTP calls.
     */
    public function testHandleWebhookHappyPath(): void
    {
        $tokenResponse = '{"access_token":"tok-abc","token_type":"Bearer","expires_in":32400}';
        $httpClient = new MockHttpClient([
            // First call: OAuth token
            new MockResponse($tokenResponse, ['http_code' => 200]),
            // Second call: webhook signature verification
            new MockResponse('{"verification_status":"SUCCESS"}', ['http_code' => 200]),
        ]);

        $gateway = $this->buildGateway(
            clientId: 'ci',
            clientSecret: 'cs',
            webhookId: 'wh-id',
            mode: 'sandbox',
            httpClient: $httpClient,
        );

        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_PAYPAL_AUTH_ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL'          => 'https://api.sandbox.paypal.com/v1/notifications/certs/cert-id',
            'HTTP_PAYPAL_TRANSMISSION_ID'   => 'tx-id-123',
            'HTTP_PAYPAL_TRANSMISSION_SIG'  => 'sig-abc',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2024-01-01T00:00:00Z',
        ], content: '{"id":"evt-1","event_type":"BILLING.SUBSCRIPTION.ACTIVATED","resource":{"id":"sub-1"}}');

        $event = $gateway->handleWebhook($request);

        self::assertNotNull($event);
        self::assertSame('paypal', $event->gateway);
        self::assertSame('BILLING.SUBSCRIPTION.ACTIVATED', $event->eventType);
        self::assertSame('evt-1', $event->gatewayEventId);
        self::assertSame('sub-1', $event->gatewaySubscriptionId);
    }

    /**
     * Network failure: MockHttpClient configured to throw a transport error on OAuth call.
     * verifyWebhookSignature must return null → handleWebhook returns null + logs error.
     */
    public function testHandleWebhookReturnsNullWhenVerifyApiUnreachable(): void
    {
        // Simulate network failure: HTTP 500 on the OAuth token request
        $httpClient = new MockHttpClient(
            new MockResponse('{"error":"server_error"}', ['http_code' => 500]),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Verify API unreachable'));

        $gateway = new PayPalGateway(
            logger: $logger,
            httpClient: $httpClient,
            clientId: 'ci',
            clientSecret: 'cs',
            webhookId: 'wh-id',
            mode: 'sandbox',
            planMapJson: '{}',
        );

        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_PAYPAL_AUTH_ALGO'        => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL'         => 'https://api.sandbox.paypal.com/v1/notifications/certs/cert-id',
            'HTTP_PAYPAL_TRANSMISSION_ID'  => 'tx-id-123',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig-abc',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2024-01-01T00:00:00Z',
        ], content: '{"id":"evt-1","event_type":"BILLING.SUBSCRIPTION.ACTIVATED","resource":{"id":"sub-1"}}');

        $result = $gateway->handleWebhook($request);

        self::assertNull($result);
    }

    /**
     * Verify-signature API returns FAILURE status → handleWebhook returns null (invalid webhook).
     */
    public function testHandleWebhookReturnsNullWhenVerificationFails(): void
    {
        $tokenResponse = '{"access_token":"tok-abc","token_type":"Bearer","expires_in":32400}';
        $httpClient = new MockHttpClient([
            // OAuth token
            new MockResponse($tokenResponse, ['http_code' => 200]),
            // Verify webhook: FAILURE
            new MockResponse('{"verification_status":"FAILURE"}', ['http_code' => 200]),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('PayPal webhook signature verification failed');

        $gateway = new PayPalGateway(
            logger: $logger,
            httpClient: $httpClient,
            clientId: 'ci',
            clientSecret: 'cs',
            webhookId: 'wh-id',
            mode: 'sandbox',
            planMapJson: '{}',
        );

        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_PAYPAL_AUTH_ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL'          => 'https://api.sandbox.paypal.com/v1/notifications/certs/cert-id',
            'HTTP_PAYPAL_TRANSMISSION_ID'   => 'tx-id-123',
            'HTTP_PAYPAL_TRANSMISSION_SIG'  => 'sig-abc',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2024-01-01T00:00:00Z',
        ], content: '{"id":"evt-1","event_type":"BILLING.SUBSCRIPTION.ACTIVATED","resource":{"id":"sub-1"}}');

        $result = $gateway->handleWebhook($request);

        self::assertNull($result);
    }

    /**
     * Invalid/missing order ID in resource → subscriptionId is empty string, but event is still returned
     * (business logic: subscription ID from resource.id, falls back to resource.billing_agreement_id, then empty).
     */
    public function testHandleWebhookHandlesMissingSubscriptionId(): void
    {
        $tokenResponse = '{"access_token":"tok-abc","token_type":"Bearer","expires_in":32400}';
        $httpClient = new MockHttpClient([
            new MockResponse($tokenResponse, ['http_code' => 200]),
            new MockResponse('{"verification_status":"SUCCESS"}', ['http_code' => 200]),
        ]);

        $gateway = $this->buildGateway(
            webhookId: 'wh-id',
            mode: 'sandbox',
            httpClient: $httpClient,
        );

        // Payload with no resource.id and no resource.billing_agreement_id
        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_PAYPAL_AUTH_ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL'          => 'https://api.sandbox.paypal.com/v1/notifications/certs/cert-id',
            'HTTP_PAYPAL_TRANSMISSION_ID'   => 'tx-id-456',
            'HTTP_PAYPAL_TRANSMISSION_SIG'  => 'sig-def',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2024-01-01T00:00:00Z',
        ], content: '{"id":"evt-2","event_type":"PAYMENT.SALE.COMPLETED","resource":{}}');

        $event = $gateway->handleWebhook($request);

        self::assertNotNull($event);
        self::assertSame('evt-2', $event->gatewayEventId);
        self::assertSame('', $event->gatewaySubscriptionId);
    }

    /**
     * getSubscriptionStatus: mocked OAuth + status API returning ACTIVE.
     */
    public function testGetSubscriptionStatusReturnsActiveForActiveSub(): void
    {
        $tokenResponse = '{"access_token":"tok-abc","token_type":"Bearer","expires_in":32400}';
        $httpClient = new MockHttpClient([
            new MockResponse($tokenResponse, ['http_code' => 200]),
            new MockResponse('{"id":"I-123","status":"ACTIVE"}', ['http_code' => 200]),
        ]);

        $gateway = $this->buildGateway(httpClient: $httpClient);

        $status = $gateway->getSubscriptionStatus('I-123');

        self::assertSame(\App\Onboarding\Payment\SubscriptionStatus::Active, $status);
    }

    /**
     * @param list<mixed> $args
     */
    private function invokePrivate(object $service, string $methodName, array $args = []): mixed
    {
        $method = new \ReflectionMethod($service, $methodName);

        return $method->invokeArgs($service, $args);
    }
}
