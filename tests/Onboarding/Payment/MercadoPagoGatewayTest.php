<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Payment\MercadoPagoGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class MercadoPagoGatewayTest extends TestCase
{
    private function buildGateway(
        string $accessToken = 'test-access-token',
        string $webhookSecret = 'test-webhook-secret',
        string $planMapJson = '{}',
    ): MercadoPagoGateway {
        return new MercadoPagoGateway(
            logger: $this->createMock(LoggerInterface::class),
            accessToken: $accessToken,
            webhookSecret: $webhookSecret,
            planMapJson: $planMapJson,
        );
    }

    public function testGetName(): void
    {
        $gateway = $this->buildGateway();

        self::assertSame('mercadopago', $gateway->getName());
    }

    public function testResolvePlanConfigResolvesKnownKey(): void
    {
        $gateway = $this->buildGateway(
            planMapJson: '{"basic_monthly":{"amount":29.9,"currency":"PEN"},"pro_annual":{"amount":799,"currency":"PEN"}}',
        );

        $config = $this->invokePrivate($gateway, 'resolvePlanConfig', ['basic', BillingCycle::Monthly]);
        self::assertSame(29.9, $config['amount']);
        self::assertSame('PEN', $config['currency']);

        $config = $this->invokePrivate($gateway, 'resolvePlanConfig', ['pro', BillingCycle::Annual]);
        self::assertSame(799, $config['amount']);
        self::assertSame('PEN', $config['currency']);
    }

    public function testResolvePlanConfigThrowsForMissingKey(): void
    {
        $gateway = $this->buildGateway(
            planMapJson: '{"basic_monthly":{"amount":29.9,"currency":"PEN"}}',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No MercadoPago pricing configured for: pro_annual');
        $this->invokePrivate($gateway, 'resolvePlanConfig', ['pro', BillingCycle::Annual]);
    }

    public function testHandleWebhookReturnsNullWhenSignatureInvalid(): void
    {
        $gateway = $this->buildGateway(webhookSecret: 'secret');

        $request = Request::create('/webhook?data.id=123', 'POST', server: [
            'HTTP_X_SIGNATURE' => 'ts=1,v1=invalid',
            'HTTP_X_REQUEST_ID' => 'req-1',
        ], content: '{"id":"evt-1","type":"subscription_preapproval","action":"authorized","data":{"id":"sub-1"}}');

        self::assertNull($gateway->handleWebhook($request));
    }

    public function testHandleWebhookReturnsPaymentEventWhenSignatureValid(): void
    {
        $secret = 'secret';
        $dataId = 'sub-1';
        $requestId = 'req-1';
        // Use a timestamp within the acceptance window (≤ 300 s in the past)
        $ts = (string) (time() - 5);
        $manifest = "id:$dataId;request-id:$requestId;ts:$ts;";
        $hash = hash_hmac('sha256', $manifest, $secret);

        $gateway = $this->buildGateway(webhookSecret: $secret);
        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_X_SIGNATURE' => "ts=$ts,v1=$hash",
            'HTTP_X_REQUEST_ID' => $requestId,
        ], content: '{"id":"evt-9","type":"subscription_preapproval","action":"authorized","data":{"id":"sub-1"}}');
        $request->query->set('data.id', 'sub-1');

        $event = $gateway->handleWebhook($request);

        self::assertNotNull($event);
        self::assertSame('mercadopago', $event->gateway);
        self::assertSame('subscription_preapproval.authorized', $event->eventType);
        self::assertSame('evt-9', $event->gatewayEventId);
        self::assertSame('sub-1', $event->gatewaySubscriptionId);
    }

    public function testVerifyWebhookSignatureRejectsStaleTimestamp(): void
    {
        $secret = 'secret';
        $dataId = 'sub-stale';
        $requestId = 'req-stale';
        // Timestamp more than 300 s in the past → replay-protection window exceeded
        $staleTs = (string) (time() - 400);
        $manifest = "id:$dataId;request-id:$requestId;ts:$staleTs;";
        $hash = hash_hmac('sha256', $manifest, $secret);

        $gateway = $this->buildGateway(webhookSecret: $secret);
        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_X_SIGNATURE' => "ts=$staleTs,v1=$hash",
            'HTTP_X_REQUEST_ID' => $requestId,
        ], content: '{"id":"evt-stale","type":"subscription_preapproval","action":"authorized","data":{"id":"sub-stale"}}');
        $request->query->set('data.id', $dataId);

        // handleWebhook returns null when verifyWebhookSignature returns null (replay-guard rejection)
        self::assertNull($gateway->handleWebhook($request));
    }

    public function testVerifyWebhookSignatureRejectsFutureTimestamp(): void
    {
        $secret = 'secret';
        $dataId = 'sub-future';
        $requestId = 'req-future';
        // Timestamp more than 60 s in the future → outside acceptance window
        $futureTs = (string) (time() + 120);
        $manifest = "id:$dataId;request-id:$requestId;ts:$futureTs;";
        $hash = hash_hmac('sha256', $manifest, $secret);

        $gateway = $this->buildGateway(webhookSecret: $secret);
        $request = Request::create('/webhook', 'POST', server: [
            'HTTP_X_SIGNATURE' => "ts=$futureTs,v1=$hash",
            'HTTP_X_REQUEST_ID' => $requestId,
        ], content: '{"id":"evt-future","type":"subscription_preapproval","action":"authorized","data":{"id":"sub-future"}}');
        $request->query->set('data.id', $dataId);

        self::assertNull($gateway->handleWebhook($request));
    }

    public function testCredentialsAreStoredViaConstructorInjection(): void
    {
        $gateway = $this->buildGateway(
            accessToken: 'APP_USR-token-123',
            webhookSecret: 'wh-secret-abc',
        );

        $reflection = new \ReflectionClass($gateway);

        $tokenProp = $reflection->getProperty('accessToken');
        self::assertSame('APP_USR-token-123', $tokenProp->getValue($gateway));

        $secretProp = $reflection->getProperty('webhookSecret');
        self::assertSame('wh-secret-abc', $secretProp->getValue($gateway));
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
