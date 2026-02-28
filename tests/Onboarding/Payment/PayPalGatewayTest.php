<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Payment\PayPalGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PayPalGatewayTest extends TestCase
{
    public function testGetNameAndBaseUrlByMode(): void
    {
        $gateway = new PayPalGateway($this->createMock(LoggerInterface::class));

        $_ENV['PAYPAL_MODE'] = 'live';
        self::assertSame('paypal', $gateway->getName());
        self::assertSame('https://api-m.paypal.com', $this->invokePrivate($gateway, 'getBaseUrl'));

        $_ENV['PAYPAL_MODE'] = 'sandbox';
        self::assertSame('https://api-m.sandbox.paypal.com', $this->invokePrivate($gateway, 'getBaseUrl'));
        unset($_ENV['PAYPAL_MODE']);
    }

    public function testResolvePayPalPlanIdAndMissingConfig(): void
    {
        $gateway = new PayPalGateway($this->createMock(LoggerInterface::class));

        $_ENV['PAYPAL_PLAN_MAP'] = '{"basic_monthly":"P-123"}';
        $planId = $this->invokePrivate($gateway, 'resolvePayPalPlanId', ['basic', BillingCycle::Monthly]);
        self::assertSame('P-123', $planId);

        try {
            $this->invokePrivate($gateway, 'resolvePayPalPlanId', ['pro', BillingCycle::Annual]);
            self::fail('Expected invalid argument exception');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('No PayPal plan ID configured', $exception->getMessage());
        }

        unset($_ENV['PAYPAL_PLAN_MAP']);
    }

    public function testGetEnvThrowsWhenVariableIsMissing(): void
    {
        $gateway = new PayPalGateway($this->createMock(LoggerInterface::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing environment variable: PAYPAL_CLIENT_ID');
        $this->invokePrivate($gateway, 'getEnv', ['PAYPAL_CLIENT_ID']);
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
