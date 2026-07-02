<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Onboarding\Payment;

use App\ControlPlane\Onboarding\Message\ProvisionTenantMessage;
use App\ControlPlane\Onboarding\Security\SecretCipher;
use App\ControlPlane\Subscription\Payment\PaymentEvent;
use App\ControlPlane\Subscription\Payment\SubscriptionManager;
use App\ControlPlane\Subscription\Payment\WebhookProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class WebhookProcessorTest extends TestCase
{
    public function testSkipsAlreadyProcessedEvent(): void
    {
        $connection = $this->connection();
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls([
            'id' => 1,
            'tenant_id' => 2,
            'status' => 'active',
            'account_id' => 3,
            'plan' => 'basic',
        ], ['id' => 99, 'processed_at' => '2026-04-24 20:00:00', 'processing_status' => 'completed']);
        $connection->expects(self::once())->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $processor = $this->processor($connection, $logger, $bus);
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', 'evt-1', 'sub-1'));
    }

    public function testBusinessActionFailureLeavesPaymentEventRetryable(): void
    {
        $connection = $this->connection();
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls([
            'id' => 15,
            'tenant_id' => 30,
            'status' => 'active',
            'account_id' => 11,
            'plan' => 'basic',
        ], ['id' => 99, 'processed_at' => null, 'processing_status' => 'in_progress']);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');

        $executedSql = [];
        $connection
            ->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []) use (&$executedSql): int {
                $executedSql[] = $sql;

                if (str_contains($sql, 'UPDATE subscriptions SET status = ?') && ($params[0] ?? null) === 'past_due') {
                    throw new \RuntimeException('transient action failure');
                }

                return 1;
            });

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );

        try {
            $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'evt-retry', 'sub-15'));
            self::fail('Expected business action failure to be rethrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('transient action failure', $e->getMessage());
        }

        self::assertTrue(
            array_any($executedSql, static fn(string $sql): bool => str_contains($sql, "processing_status = 'failed'")),
            'Failure path must mark the payment event as failed for retry.',
        );
        self::assertFalse(
            array_any($executedSql, static fn(string $sql): bool => str_contains(
                $sql,
                "processing_status = 'completed'",
            )),
            'Failed business actions must not complete the payment event.',
        );
        self::assertTrue(
            array_any($executedSql, static fn(string $sql): bool => str_contains($sql, 'processed_at = NULL')),
            'Failed payment events must keep processed_at NULL so redelivery can retry.',
        );
    }

    public function testIgnoresUnhandledEventType(): void
    {
        $connection = $this->connection();
        $connection->expects(self::never())->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'UNHANDLED.TYPE', 'evt-2', 'sub-1'));
    }

    public function testLogsUnknownSubscriptionAndPersistsEvent(): void
    {
        $connection = $this->connection();
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
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 8,
                'tenant_id' => 20,
                'status' => 'active',
                'account_id' => 11,
                'plan' => 'basic',
            ]);
        $connection->method('fetchOne')->willReturn('monthly');
        $connection->expects(self::exactly(5))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.CANCELLED', 'evt-4', 'sub-8'));
    }

    public function testPaymentCheckApprovedRenewsPastDueSubscription(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn('monthly');
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 9,
                'tenant_id' => 0,
                'status' => 'past_due',
                'account_id' => 11,
                'plan' => 'basic',
            ]);
        $connection->expects(self::exactly(4))->method('executeStatement');

        $processor = $this->processor(
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
        $processor->process($this->event('mercadopago', 'payment.created', 'evt-5', 'sub-9', ['data' => [
            'status' => 'approved',
        ]]));
    }

    public function testActivateEventTriggersProvisioningForPendingTenant(): void
    {
        $cipher = new SecretCipher('test-secret');
        $protectedPassword = $cipher->encrypt('secret');
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn('monthly');
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 10,
                'tenant_id' => 0,
                'status' => 'pending',
                'account_id' => 13,
                'plan' => 'pro',
                'provision_id' => 55,
            ],
            ['id' => 99, 'processed_at' => null, 'processing_status' => 'in_progress'],
            [
                'id' => 55,
                'account_id' => 13,
                'tenant_name' => 'acme',
                'domain' => 'acme.example.test',
                'admin_password' => $protectedPassword,
            ],
        );
        $connection->expects(self::exactly(5))->method('executeStatement');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProvisionTenantMessage $message) use ($cipher): bool {
                return (
                    $message->adminPasswordSecret !== 'secret'
                    && $cipher->decrypt($message->adminPasswordSecret) === 'secret'
                );
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = $this->processor($connection, $this->createMock(LoggerInterface::class), $bus, $cipher);
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', 'evt-6', 'sub-10'));
    }

    public function testActivateEventForAlreadyLinkedSubscriptionDoesNotDispatchProvisioning(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn('monthly');
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls([
            'id' => 11,
            'tenant_id' => 44,
            'status' => 'pending',
            'account_id' => 13,
            'plan' => 'pro',
            'provision_id' => 55,
        ], ['id' => 100, 'processed_at' => null, 'processing_status' => 'failed']);

        $executedSql = [];
        $connection
            ->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedSql): int {
                $executedSql[] = $sql;

                return 1;
            });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $processor = $this->processor($connection, $logger, $bus);
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', 'evt-retry-linked', 'sub-11'));

        self::assertFalse(
            array_any($executedSql, static fn(string $sql): bool => str_contains($sql, 'tenant_provisions')),
            'An already-linked subscription must not enqueue or mutate provisioning rows during retry recovery.',
        );
    }

    // ------------------------------------------------------------------ handlePastDue

    public function testPastDueEventMarkesSubscriptionPastDue(): void
    {
        $connection = $this->connection();
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 15,
                'tenant_id' => 30,
                'status' => 'active',
                'account_id' => 11,
                'plan' => 'basic',
            ]);
        // executeStatement calls: reserve + mark in_progress + markPastDue + complete
        $connection->expects(self::exactly(4))->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'evt-7', 'sub-15'));
    }

    public function testPastDueEventWithZeroTenantIdDoesNotSuspendTenant(): void
    {
        $connection = $this->connection();
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 16,
                'tenant_id' => 0,
                'status' => 'active',
                'account_id' => 11,
                'plan' => 'basic',
            ]);
        // reserve + mark in_progress + markPastDue + complete — no suspendTenant call
        $connection->expects(self::exactly(4))->method('executeStatement');

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
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 20,
                'tenant_id' => 40,
                'status' => 'active',
                'account_id' => 12,
                'plan' => 'pro',
            ]);
        // executeStatement calls: reserve + mark in_progress + suspend + suspendTenant + complete
        $connection->expects(self::exactly(5))->method('executeStatement');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('paypal', 'BILLING.SUBSCRIPTION.SUSPENDED', 'evt-9', 'sub-20'));
    }

    public function testSuspendEventWithZeroTenantIdDoesNotSuspendTenant(): void
    {
        $connection = $this->connection();
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 21,
                'tenant_id' => 0,
                'status' => 'active',
                'account_id' => 12,
                'plan' => 'pro',
            ]);
        // reserve + mark in_progress + suspend + complete — no suspendTenant
        $connection->expects(self::exactly(4))->method('executeStatement');

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
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 22,
                'tenant_id' => 50,
                'status' => 'active',
                'account_id' => 12,
                'plan' => 'pro',
            ]);
        $connection->expects(self::exactly(5))->method('executeStatement');

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
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 25,
                'tenant_id' => 0,
                'status' => 'active',
                'account_id' => 11,
                'plan' => 'basic',
            ]);
        // reserve + mark in_progress + cancel + complete — no suspendTenant
        $connection->expects(self::exactly(4))->method('executeStatement');

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
            'BILLING.SUBSCRIPTION.ACTIVATED' => 'activate',
            'PAYMENT.SALE.COMPLETED' => 'renew',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => 'past_due',
            'BILLING.SUBSCRIPTION.CANCELLED' => 'cancel',
            'BILLING.SUBSCRIPTION.SUSPENDED' => 'suspend',
            'CUSTOMER.DISPUTE.CREATED' => 'suspend',
        ];

        $connection = $this->connection();
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
            'subscription_preapproval.cancelled' => 'cancel',
            'subscription_preapproval.paused' => 'suspend',
        ];

        $connection = $this->connection();
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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $processor = $this->processor($connection, $logger, $this->createMock(MessageBusInterface::class));
        $processor->process($this->event('stripe', 'customer.subscription.deleted', 'evt-s1', 'sub-xyz'));
    }

    private function processor(
        Connection $connection,
        LoggerInterface $logger,
        MessageBusInterface $bus,
        ?SecretCipher $cipher = null,
    ): WebhookProcessor {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $subscriptionManager = new SubscriptionManager($registry, $logger);

        return new WebhookProcessor(
            $subscriptionManager,
            $registry,
            $bus,
            $logger,
            $cipher ?? new SecretCipher('test-secret'),
        );
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
            payload: $payload,
        );
    }

    /**
     * @return MockObject&Connection
     */
    private function connection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('lastInsertId')->willReturn('99');

        return $connection;
    }
}
