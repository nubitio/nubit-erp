<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Payment\PayPalGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class PayPalGatewayTest extends TestCase
{
    private function buildGateway(
        string $clientId = 'test-client-id',
        string $clientSecret = 'test-client-secret',
        string $webhookId = 'test-webhook-id',
        string $mode = 'sandbox',
        string $planMapJson = '{}',
    ): PayPalGateway {
        return new PayPalGateway(
            logger: $this->createMock(LoggerInterface::class),
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

    public function testHandleWebhookReturnsNullWhenVerifyApiUnreachable(): void
    {
        // When the PayPal Verify API is unreachable (OAuth token request fails via curl
        // because there is no real network / PayPal server in the test environment),
        // verifyWebhookSignature() must return null — not false — and handleWebhook()
        // must propagate that as a null PaymentEvent (graceful failure, no exception).
        $gateway = $this->buildGateway(
            clientId: 'ci',
            clientSecret: 'cs',
            webhookId: 'wh-id',
            mode: 'sandbox',
        );

        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_PAYPAL_AUTH_ALGO'        => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL'         => 'https://api.sandbox.paypal.com/v1/notifications/certs/cert-id',
            'HTTP_PAYPAL_TRANSMISSION_ID'  => 'tx-id-123',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig-abc',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2024-01-01T00:00:00Z',
        ], content: '{"id":"evt-1","event_type":"BILLING.SUBSCRIPTION.ACTIVATED","resource":{"id":"sub-1"}}');

        // The curl call to the sandbox OAuth endpoint will fail in unit-test context;
        // the gateway must return null (not false, not throw).
        $result = $gateway->handleWebhook($request);

        self::assertNull($result);
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
