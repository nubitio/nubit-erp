<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Payment;

use App\ControlPlane\Subscription\Payment\BillingCycle;
use App\ControlPlane\Subscription\Payment\PaymentEvent;
use App\ControlPlane\Subscription\Payment\SubscriptionManager;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SubscriptionManagerTest extends TestCase
{
    public function testCreateAndCreatePendingReturnInsertedIds(): void
    {
        $connection = $this->connection();
        $insertedRows = [];
        $connection->expects(self::exactly(2))
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data) use (&$insertedRows): int {
                $insertedRows[] = [$table, $data];

                return 1;
            });
        $connection->method('lastInsertId')->willReturnOnConsecutiveCalls('11', '12');

        $manager = $this->manager($connection);
        $created = $manager->create(1, 2, 'basic', BillingCycle::Monthly, 'paypal', 'sub-1', 'cust-1');
        $pending = $manager->createPending(2, 9, 'pro', BillingCycle::Annual, 'mercadopago', 'sub-2');

        self::assertSame(11, $created['subscription_id']);
        self::assertSame(12, $pending['subscription_id']);
        self::assertSame(1, $insertedRows[0][1]['tenant_id']);
        self::assertNull($insertedRows[1][1]['tenant_id']);
        self::assertSame(9, $insertedRows[1][1]['provision_id']);
    }

    public function testActivateAndRenewUseResolvedBillingCycle(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('annual', 'monthly');
        $connection->expects(self::exactly(2))->method('executeStatement');

        $manager = $this->manager($connection);
        $manager->activate(5);
        $manager->renew(5);
    }

    public function testStatusTransitionsAndTenantLinkArePersisted(): void
    {
        $connection = $this->connection();
        $connection->expects(self::exactly(4))->method('executeStatement');

        $manager = $this->manager($connection);
        $manager->markPastDue(7);
        $manager->cancel(7);
        $manager->suspend(7);
        $manager->linkTenant(7, 20);
    }

    public function testLinkPendingProvisionSubscriptionsLinksOnlyUnlinkedRowsBySubscriptionId(): void
    {
        $connection = $this->connection();
        $linked = [];
        $connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with(
                'SELECT id FROM subscriptions WHERE provision_id = ? AND tenant_id IS NULL',
                [55],
                [\Doctrine\DBAL\ParameterType::INTEGER]
            )
            ->willReturn(['7', '8']);
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params, array $types) use (&$linked): int {
                self::assertSame('UPDATE subscriptions SET tenant_id = ?, updated_at = NOW() WHERE id = ?', $sql);
                self::assertSame([\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER], $types);
                $linked[] = $params;

                return 1;
            });

        $manager = $this->manager($connection);

        self::assertSame(2, $manager->linkPendingProvisionSubscriptions(55, 20));
        self::assertSame([[20, 7], [20, 8]], $linked);
    }

    public function testLinkPendingProvisionSubscriptionsDoesNotOverwriteAlreadyLinkedRows(): void
    {
        $connection = $this->connection();
        $connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with(
                'SELECT id FROM subscriptions WHERE provision_id = ? AND tenant_id IS NULL',
                [55],
                [\Doctrine\DBAL\ParameterType::INTEGER]
            )
            ->willReturn([]);
        $connection->expects(self::never())->method('executeStatement');

        $manager = $this->manager($connection);

        self::assertSame(0, $manager->linkPendingProvisionSubscriptions(55, 20));
    }

    public function testFailedPaymentEventRetryRefreshesLinkageWithoutCompletingOrDuplicatingEvent(): void
    {
        $connection = $this->connection();
        $connection->method('lastInsertId')->willReturn('45');
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                'SELECT id, processed_at, processing_status FROM payment_events WHERE id = ? FOR UPDATE',
                [45],
                [\Doctrine\DBAL\ParameterType::INTEGER]
            )
            ->willReturn([
                'id' => 45,
                'processed_at' => null,
                'processing_status' => 'failed',
            ]);

        $executions = [];
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params, array $types) use (&$executions): int {
                $executions[] = [$sql, $params, $types];

                return 1;
            });

        $manager = $this->manager($connection);

        self::assertSame(45, $manager->beginPaymentEventProcessing(
            new PaymentEvent('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', 'evt-retry', 'sub-1', ['ok' => true]),
            7,
            20,
        ));

        self::assertStringContainsString('ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)', $executions[0][0]);
        self::assertStringContainsString("processing_status = 'in_progress'", $executions[1][0]);
        self::assertSame([7, 20, 'BILLING.SUBSCRIPTION.ACTIVATED'], array_slice($executions[1][1], 0, 3));
        self::assertSame([\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER], array_slice($executions[1][2], 0, 2));
    }

    public function testFindMethodsReturnExpectedRows(): void
    {
        $connection = $this->connection();
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            false,
            ['id' => 2, 'gateway' => 'paypal'],
            false,
            ['id' => 4, 'status' => 'active'],
            ['id' => 5, 'status' => 'past_due']
        );

        $manager = $this->manager($connection);
        self::assertNull($manager->findByGatewaySubscriptionId('paypal', 'sub-404'));
        self::assertSame(2, $manager->findByGatewaySubscriptionId('paypal', 'sub-2')['id']);
        self::assertNull($manager->findById(3));
        self::assertSame('active', $manager->findById(4)['status']);
        self::assertSame('past_due', $manager->findActiveByTenant(8)['status']);
    }

    public function testGracePeriodQueriesAndEventProcessedFlag(): void
    {
        $connection = $this->connection();
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [['id' => 1]],
            [['id' => 2]]
        );
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(false, 1);

        $manager = $this->manager($connection);
        self::assertCount(1, $manager->findExpiredGracePeriod());
        self::assertCount(1, $manager->findExpiringGracePeriod(3));
        self::assertFalse($manager->isEventProcessed('paypal', 'evt-1'));
        self::assertTrue($manager->isEventProcessed('paypal', 'evt-1'));
    }

    public function testLogPaymentEventSuccessAndFailure(): void
    {
        $connection = $this->connection();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params): int {
                if (($params[4] ?? '') === 'evt-fail') {
                    throw new \RuntimeException('db error');
                }

                return 1;
            });

        $manager = $this->manager($connection, $logger);
        $manager->logPaymentEvent(
            new PaymentEvent('paypal', 'payment.approved', 'evt-ok', 'sub-1', ['ok' => true]),
            1,
            2
        );
        $manager->logPaymentEvent(
            new PaymentEvent('paypal', 'payment.failed', 'evt-fail', 'sub-2', ['ok' => false]),
            1,
            2
        );
    }

    public function testPaymentEventProcessingLifecycleReservesCompletesAndFails(): void
    {
        $connection = $this->connection();
        $connection->method('lastInsertId')->willReturn('42');
        $connection->method('fetchAssociative')->willReturn(['id' => 42, 'processed_at' => null, 'processing_status' => 'failed']);
        $connection->expects(self::exactly(4))->method('executeStatement');

        $manager = $this->manager($connection);
        $event = new PaymentEvent('paypal', 'payment.approved', 'evt-life', 'sub-1', ['ok' => true]);

        self::assertSame(42, $manager->beginPaymentEventProcessing($event, 1, 2));
        $manager->completePaymentEvent(42);
        $manager->failPaymentEvent($event, 1, 2, 'transient failure');
    }

    public function testPaymentEventProcessingReturnsNullForCompletedDuplicate(): void
    {
        $connection = $this->connection();
        $connection->method('lastInsertId')->willReturn('43');
        $connection->method('fetchAssociative')->willReturn([
            'id' => 43,
            'processed_at' => '2026-04-24 20:00:00',
            'processing_status' => 'completed',
        ]);
        $connection->expects(self::once())->method('executeStatement');

        $manager = $this->manager($connection);

        self::assertNull($manager->beginPaymentEventProcessing(
            new PaymentEvent('paypal', 'payment.approved', 'evt-duplicate', 'sub-1', ['ok' => true]),
            1,
            2,
        ));
    }

    public function testPaymentEventProcessingReservesInProgressDuplicateForLockedProcessor(): void
    {
        $connection = $this->connection();
        $connection->method('lastInsertId')->willReturn('44');
        $connection->method('fetchAssociative')->willReturn([
            'id' => 44,
            'processed_at' => null,
            'processing_status' => 'in_progress',
        ]);

        $executedSql = [];
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedSql): int {
                $executedSql[] = $sql;

                return 1;
            });

        $manager = $this->manager($connection);

        self::assertSame(44, $manager->beginPaymentEventProcessing(
            new PaymentEvent('paypal', 'payment.approved', 'evt-in-progress', 'sub-1', ['ok' => true]),
            1,
            2,
        ));
        self::assertTrue(
            array_any($executedSql, static fn (string $sql): bool => str_contains($sql, "processing_status = 'in_progress'")),
            'A locked in-progress duplicate should be reserved by the current processor rather than skipped.'
        );
    }

    public function testActivateFallsBackToMonthlyWhenBillingCycleNotFound(): void
    {
        $connection = $this->connection();
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects(self::once())->method('executeStatement');

        $manager = $this->manager($connection);
        $manager->activate(99);
    }

    /**
     * @return MockObject&Connection
     */
    private function connection(): Connection
    {
        return $this->createMock(Connection::class);
    }

    private function manager(Connection $connection, ?LoggerInterface $logger = null): SubscriptionManager
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        return new SubscriptionManager(
            $registry,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }
}
