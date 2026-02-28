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
    public function testGetNameAndResolvePlanConfig(): void
    {
        $gateway = new MercadoPagoGateway($this->createMock(LoggerInterface::class));
        self::assertSame('mercadopago', $gateway->getName());

        $_ENV['MERCADOPAGO_PLAN_MAP'] = '{"basic_monthly":{"amount":29.9,"currency":"PEN"}}';
        $config = $this->invokePrivate($gateway, 'resolvePlanConfig', ['basic', BillingCycle::Monthly]);
        self::assertSame(29.9, $config['amount']);
        self::assertSame('PEN', $config['currency']);

        try {
            $this->invokePrivate($gateway, 'resolvePlanConfig', ['pro', BillingCycle::Annual]);
            self::fail('Expected invalid argument exception');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('No MercadoPago pricing configured', $exception->getMessage());
        }

        unset($_ENV['MERCADOPAGO_PLAN_MAP']);
    }

    public function testGetEnvThrowsWhenMissing(): void
    {
        $gateway = new MercadoPagoGateway($this->createMock(LoggerInterface::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing environment variable: MERCADOPAGO_ACCESS_TOKEN');
        $this->invokePrivate($gateway, 'getEnv', ['MERCADOPAGO_ACCESS_TOKEN']);
    }

    public function testHandleWebhookReturnsNullWhenSignatureInvalid(): void
    {
        $_ENV['MERCADOPAGO_WEBHOOK_SECRET'] = 'secret';
        $gateway = new MercadoPagoGateway($this->createMock(LoggerInterface::class));

        $request = Request::create('/webhook?data.id=123', 'POST', server: [
            'HTTP_X_SIGNATURE' => 'ts=1,v1=invalid',
            'HTTP_X_REQUEST_ID' => 'req-1',
        ], content: '{"id":"evt-1","type":"subscription_preapproval","action":"authorized","data":{"id":"sub-1"}}');

        self::assertNull($gateway->handleWebhook($request));
        unset($_ENV['MERCADOPAGO_WEBHOOK_SECRET']);
    }

    public function testHandleWebhookReturnsPaymentEventWhenSignatureValid(): void
    {
        $secret = 'secret';
        $_ENV['MERCADOPAGO_WEBHOOK_SECRET'] = $secret;
        $dataId = 'sub-1';
        $requestId = 'req-1';
        $ts = '1700000000';
        $manifest = "id:$dataId;request-id:$requestId;ts:$ts;";
        $hash = hash_hmac('sha256', $manifest, $secret);

        $gateway = new MercadoPagoGateway($this->createMock(LoggerInterface::class));
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
        unset($_ENV['MERCADOPAGO_WEBHOOK_SECRET']);
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
