<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\ControlPlane\Onboarding\Message\ProvisionTenantMessage;
use App\ControlPlane\Subscription\Payment\PaymentEvent;
use App\ControlPlane\Subscription\Payment\SubscriptionManager;
use App\ControlPlane\Subscription\Payment\WebhookProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class WebhookProcessorTest extends TestCase
{
    public function testSkipsAlreadyProcessedEvent(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $processor = $this->processor($connection, $logger, $bus);
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', 'evt-1', 'sub-1'));
    }

    public function testIgnoresUnhandledEventType(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'UNHANDLED.TYPE', 'evt-2', 'sub-1'));
    }

    public function testLogsUnknownSubscriptionAndPersistsEvent(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects(self::once())->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.CANCELLED', 'evt-3', 'sub-404'));
    }

    public function testCancelEventCancelsSubscriptionAndSuspendsTenant(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 8,
            'tenant_id' => 20,
            'status' => 'active',
            'account_id' => 11,
            'plan' => 'basic',
        ]);
        $connection->expects(self::exactly(3))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class)
        );
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.CANCELLED', 'evt-4', 'sub-8'));
    }

    public function testPaymentCheckApprovedRenewsPastDueSubscription(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(false, 'monthly');
        $connection->method('fetchAssociative')->willReturn([
            'id' => 9,
            'tenant_id' => 0,
            'status' => 'past_due',
            'account_id' => 11,
            'plan' => 'basic',
        ]);
        $connection->expects(self::exactly(2))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class)
        );
        $processor->process($this->event(
            'mercadopago',
            'payment.created',
            'evt-5',
            'sub-9',
            ['data' => ['status' => 'approved']]
        ));
    }

    public function testActivateEventTriggersProvisioningForPendingTenant(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(false, 'monthly');
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 10,
                'tenant_id' => 0,
                'status' => 'pending',
                'account_id' => 13,
                'plan' => 'pro',
                'provision_id' => 55,
            ],
            [
                'id' => 55,
                'account_id' => 13,
                'tenant_name' => 'acme',
                'domain' => 'acme.example.test',
                'admin_password' => 'secret',
            ]
        );
        $connection->expects(self::exactly(3))->method('executeStatement');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProvisionTenantMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = $this->processor($connection, $this->createMock(LoggerInterface::class), $bus);
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', 'evt-6', 'sub-10'));
    }

    // ------------------------------------------------------------------ handlePastDue

    public function testPastDueEventMarkesSubscriptionPastDue(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 15,
            'tenant_id' => 30,
            'status' => 'active',
            'account_id' => 11,
            'plan' => 'basic',
        ]);
        // executeStatement calls: logPaymentEvent (INSERT) + markPastDue (UPDATE)
        $connection->expects(self::exactly(2))->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'evt-7', 'sub-15'));
    }

    public function testPastDueEventWithZeroTenantIdDoesNotSuspendTenant(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 16,
            'tenant_id' => 0,
            'status' => 'active',
            'account_id' => 11,
            'plan' => 'basic',
        ]);
        // Only logPaymentEvent + markPastDue — no suspendTenant call
        $connection->expects(self::exactly(2))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'evt-8', 'sub-16'));
    }

    // ------------------------------------------------------------------ handleSuspend

    public function testSuspendEventSuspendsSubscriptionAndTenant(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 20,
            'tenant_id' => 40,
            'status' => 'active',
            'account_id' => 12,
            'plan' => 'pro',
        ]);
        // executeStatement calls: logPaymentEvent (INSERT) + suspend (UPDATE) + suspendTenant (UPDATE)
        $connection->expects(self::exactly(3))->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.SUSPENDED', 'evt-9', 'sub-20'));
    }

    public function testSuspendEventWithZeroTenantIdDoesNotSuspendTenant(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 21,
            'tenant_id' => 0,
            'status' => 'active',
            'account_id' => 12,
            'plan' => 'pro',
        ]);
        // Only logPaymentEvent + suspend — no suspendTenant
        $connection->expects(self::exactly(2))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.SUSPENDED', 'evt-10', 'sub-21'));
    }

    public function testDisputeCreatedEventAlsoTriggersSupend(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 22,
            'tenant_id' => 50,
            'status' => 'active',
            'account_id' => 12,
            'plan' => 'pro',
        ]);
        $connection->expects(self::exactly(3))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
        $processor->process($this->event('paypal', 'CUSTOMER.DISPUTE.CREATED', 'evt-11', 'sub-22'));
    }

    // ------------------------------------------------------------------ handleCancel without tenant

    public function testCancelEventWithZeroTenantIdDoesNotSuspendTenant(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 25,
            'tenant_id' => 0,
            'status' => 'active',
            'account_id' => 11,
            'plan' => 'basic',
        ]);
        // Only logPaymentEvent + cancel — no suspendTenant
        $connection->expects(self::exactly(2))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.CANCELLED', 'evt-12', 'sub-25'));
    }

    // ------------------------------------------------------------------ resolveAction / dispatch table

    public function testResolveActionCoversAllFivePayPalEvents(): void
    {
        $knownPayPalEvents = [
            'BILLING.SUBSCRIPTION.ACTIVATED'        => 'activate',
            'PAYMENT.SALE.COMPLETED'                => 'renew',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED'   => 'past_due',
            'BILLING.SUBSCRIPTION.CANCELLED'        => 'cancel',
            'BILLING.SUBSCRIPTION.SUSPENDED'        => 'suspend',
            'CUSTOMER.DISPUTE.CREATED'              => 'suspend',
        ];

        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        // Each known event should trigger a warning (unknown subscription) — not a debug (unhandled)
        $logger->expects(self::exactly(count($knownPayPalEvents)))->method('warning');
        $logger->expects(self::never())->method('debug');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));

        foreach ($knownPayPalEvents as $eventType => $_action) {
            $processor->process($this->event('paypal', $eventType, 'evt-' . $eventType, 'sub-unknown'));
        }
    }

    public function testResolveActionCoversAllMercadoPagoEvents(): void
    {
        $knownMpEvents = [
            'subscription_preapproval.authorized' => 'activate',
            'subscription_preapproval.cancelled'  => 'cancel',
            'subscription_preapproval.paused'     => 'suspend',
        ];

        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(count($knownMpEvents)))->method('warning');
        $logger->expects(self::never())->method('debug');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));

        foreach ($knownMpEvents as $eventType => $_action) {
            $processor->process($this->event('mercadopago', $eventType, 'evt-mp-' . $eventType, 'sub-unknown'));
        }
    }

    public function testUnknownGatewayExitsCleanlyWithNoException(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('stripe', 'customer.subscription.deleted', 'evt-s1', 'sub-xyz'));
    }

    private function processor(
        Connection $connection,
        LoggerInterface $logger,
        MessageBusInterface $bus,
    ): WebhookProcessor {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $subscriptionManager = new SubscriptionManager($registry, $logger);

        return new WebhookProcessor($subscriptionManager, $registry, $bus, $logger);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function event(
        string $gateway,
        string $eventType,
        string $eventId,
        string $subscriptionId,
        array $payload = [],
    ): PaymentEvent {
        return new PaymentEvent(
            gateway: $gateway,
            eventType: $eventType,
            gatewayEventId: $eventId,
            gatewaySubscriptionId: $subscriptionId,
            payload: $payload
        );
    }

    /**
     * @return MockObject&Connection
     */
    private function connection(): Connection
    {
        return $this->createMock(Connection::class);
    }
}
