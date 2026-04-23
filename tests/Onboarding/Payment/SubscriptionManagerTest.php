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
        $connection->expects(self::exactly(2))->method('insert');
        $connection->method('lastInsertId')->willReturnOnConsecutiveCalls('11', '12');

        $manager = $this->manager($connection);
        $created = $manager->create(1, 2, 'basic', BillingCycle::Monthly, 'paypal', 'sub-1', 'cust-1');
        $pending = $manager->createPending(2, 9, 'pro', BillingCycle::Annual, 'mercadopago', 'sub-2');

        self::assertSame(11, $created['subscription_id']);
        self::assertSame(12, $pending['subscription_id']);
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
