<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Subscription;

use App\ControlPlane\Subscription\Payment\BillingCycle;
use App\ControlPlane\Subscription\Payment\SubscriptionManager;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SubscriptionManagerTest extends TestCase
{
    private function makeManager(Connection $conn): SubscriptionManager
    {
        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($conn);

        return new SubscriptionManager($registry, new NullLogger());
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function testCreateInsertsRowAndReturnsSubscriptionId(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('insert')->with('subscriptions', self::arrayHasKey('status'));
        $conn->method('lastInsertId')->willReturn('7');

        $mgr = $this->makeManager($conn);
        $result = $mgr->create(1, 2, 'starter', BillingCycle::Monthly, 'stripe', 'sub_abc');

        self::assertSame(7, $result['subscription_id']);
    }

    public function testCreatePendingInsertsRowWithNullTenantId(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('insert')
            ->with('subscriptions', self::callback(static fn(array $d) => $d['tenant_id'] === null));
        $conn->method('lastInsertId')->willReturn('3');

        $mgr = $this->makeManager($conn);
        $result = $mgr->createPending(1, 5, 'pro', BillingCycle::Annual, 'stripe', 'sub_xyz');

        self::assertSame(3, $result['subscription_id']);
    }

    // ── status transitions ────────────────────────────────────────────────────

    public function testActivateExecutesUpdateWithActiveStatus(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturn('monthly');
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('UPDATE subscriptions'),
                self::callback(static fn(array $p) => $p[0] === 'active'),
            );

        $this->makeManager($conn)->activate(10);
    }

    public function testMarkPastDueExecutesUpdateWithPastDueStatus(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::anything(), self::callback(static fn(array $p) => $p[0] === 'past_due'));

        $this->makeManager($conn)->markPastDue(10);
    }

    public function testCancelExecutesUpdateWithCancelledStatus(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('cancelled_at'), self::callback(static fn(array $p) => $p[0] === 'cancelled'));

        $this->makeManager($conn)->cancel(10);
    }

    public function testSuspendExecutesUpdateWithSuspendedStatus(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::anything(), self::callback(static fn(array $p) => $p[0] === 'suspended'));

        $this->makeManager($conn)->suspend(10);
    }

    // ── linkTenant ────────────────────────────────────────────────────────────

    public function testLinkTenantExecutesUpdate(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('tenant_id'),
                self::callback(static fn(array $p) => $p[0] === 42 && $p[1] === 10),
            );

        $this->makeManager($conn)->linkTenant(10, 42);
    }

    public function testLinkPendingProvisionSubscriptionsLinksEachFound(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchFirstColumn')->willReturn(['5', '6']);
        // 2 linkTenant calls → 2 executeStatement calls
        $conn->expects(self::exactly(2))->method('executeStatement');
        $conn->method('lastInsertId')->willReturn('0');

        $mgr = $this->makeManager($conn);
        $count = $mgr->linkPendingProvisionSubscriptions(99, 42);

        self::assertSame(2, $count);
    }

    // ── findByGatewaySubscriptionId ───────────────────────────────────────────

    public function testFindByGatewaySubscriptionIdReturnsMappedRow(): void
    {
        $row = ['id' => 7, 'status' => 'active', 'gateway' => 'stripe'];

        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')->willReturn($row);

        $result = $this->makeManager($conn)->findByGatewaySubscriptionId('stripe', 'sub_abc');

        self::assertSame($row, $result);
    }

    public function testFindByGatewaySubscriptionIdReturnsNullWhenNotFound(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')->willReturn(false);

        $result = $this->makeManager($conn)->findByGatewaySubscriptionId('stripe', 'sub_xyz');

        self::assertNull($result);
    }

    // ── BillingCycle ──────────────────────────────────────────────────────────

    public function testBillingCycleMonthlyIsOneMonth(): void
    {
        self::assertSame(1, BillingCycle::Monthly->months());
    }

    public function testBillingCycleAnnualIsTwelveMonths(): void
    {
        self::assertSame(12, BillingCycle::Annual->months());
    }
}
