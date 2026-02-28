<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\Onboarding\Message\ProvisionTenantMessage;
use App\Onboarding\Payment\PaymentEvent;
use App\Onboarding\Payment\SubscriptionManager;
use App\Onboarding\Payment\WebhookProcessor;
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
