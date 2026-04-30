<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Subscription;

use App\ControlPlane\Subscription\Payment\BillingCycle;
use App\ControlPlane\Subscription\Payment\CreateSubscriptionResult;
use App\ControlPlane\Subscription\Payment\PaymentEvent;
use App\ControlPlane\Subscription\Payment\SubscriptionStatus;
use PHPUnit\Framework\TestCase;

final class SubscriptionDomainTest extends TestCase
{
    // ── BillingCycle ──────────────────────────────────────────────────────────

    public function testBillingCycleMonthlyValues(): void
    {
        self::assertSame(1, BillingCycle::Monthly->months());
        self::assertSame(1, BillingCycle::Monthly->chargeableMonths());
    }

    public function testBillingCycleAnnualValues(): void
    {
        self::assertSame(12, BillingCycle::Annual->months());
        self::assertSame(10, BillingCycle::Annual->chargeableMonths()); // pay 10 get 12
    }

    public function testBillingCycleAnnualDiscountIs2Months(): void
    {
        $saved = BillingCycle::Annual->months() - BillingCycle::Annual->chargeableMonths();
        self::assertSame(2, $saved);
    }

    public function testBillingCycleEnumValues(): void
    {
        self::assertSame('monthly', BillingCycle::Monthly->value);
        self::assertSame('annual', BillingCycle::Annual->value);
    }

    // ── SubscriptionStatus ────────────────────────────────────────────────────

    public function testSubscriptionStatusValues(): void
    {
        self::assertSame('pending', SubscriptionStatus::Pending->value);
        self::assertSame('active', SubscriptionStatus::Active->value);
        self::assertSame('past_due', SubscriptionStatus::PastDue->value);
        self::assertSame('cancelled', SubscriptionStatus::Cancelled->value);
        self::assertSame('suspended', SubscriptionStatus::Suspended->value);
    }

    public function testSubscriptionStatusFromValue(): void
    {
        self::assertSame(SubscriptionStatus::Active, SubscriptionStatus::from('active'));
        self::assertSame(SubscriptionStatus::Cancelled, SubscriptionStatus::from('cancelled'));
    }

    // ── CreateSubscriptionResult ──────────────────────────────────────────────

    public function testCreateSubscriptionResultHoldsFields(): void
    {
        $r = new CreateSubscriptionResult(
            gatewaySubscriptionId: 'sub-abc',
            gatewayCustomerId: 'cust-xyz',
            approvalUrl: 'https://paypal.com/approve/sub-abc',
        );

        self::assertSame('sub-abc', $r->gatewaySubscriptionId);
        self::assertSame('cust-xyz', $r->gatewayCustomerId);
        self::assertSame('https://paypal.com/approve/sub-abc', $r->approvalUrl);
    }

    // ── PaymentEvent ──────────────────────────────────────────────────────────

    public function testPaymentEventHoldsFields(): void
    {
        $payload = ['amount' => '99.00', 'currency' => 'USD'];
        $e = new PaymentEvent(
            gateway: 'paypal',
            eventType: 'BILLING.SUBSCRIPTION.ACTIVATED',
            gatewayEventId: 'evt-001',
            gatewaySubscriptionId: 'sub-001',
            payload: $payload,
        );

        self::assertSame('paypal', $e->gateway);
        self::assertSame('BILLING.SUBSCRIPTION.ACTIVATED', $e->eventType);
        self::assertSame('evt-001', $e->gatewayEventId);
        self::assertSame('sub-001', $e->gatewaySubscriptionId);
        self::assertSame($payload, $e->payload);
    }
}
