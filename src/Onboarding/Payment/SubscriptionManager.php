<?php

declare(strict_types=1);

namespace App\Onboarding\Payment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;

final readonly class SubscriptionManager
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{subscription_id:int}
     */
    public function create(
        int $tenantId,
        int $accountId,
        string $plan,
        BillingCycle $billingCycle,
        string $gateway,
        string $gatewaySubscriptionId,
        string $gatewayCustomerId = '',
    ): array {
        $conn = $this->cp();
        $conn->insert('subscriptions', [
            'tenant_id' => $tenantId,
            'account_id' => $accountId,
            'plan' => $plan,
            'billing_cycle' => $billingCycle->value,
            'gateway' => $gateway,
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'gateway_customer_id' => $gatewayCustomerId,
            'status' => 'pending',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return ['subscription_id' => (int) $conn->lastInsertId()];
    }

    /**
     * Create a subscription record before the tenant exists (pre-provisioning).
     *
     * @return array{subscription_id:int}
     */
    public function createPending(
        int $accountId,
        string $plan,
        BillingCycle $billingCycle,
        string $gateway,
        string $gatewaySubscriptionId,
        string $gatewayCustomerId = '',
    ): array {
        $conn = $this->cp();
        $conn->insert('subscriptions', [
            'tenant_id' => 0,
            'account_id' => $accountId,
            'plan' => $plan,
            'billing_cycle' => $billingCycle->value,
            'gateway' => $gateway,
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'gateway_customer_id' => $gatewayCustomerId,
            'status' => 'pending',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return ['subscription_id' => (int) $conn->lastInsertId()];
    }

    public function activate(int $subscriptionId): void
    {
        $cycle = $this->resolveBillingCycle($subscriptionId);
        $now = new \DateTimeImmutable();
        $periodEnd = $now->modify(sprintf('+%d months', $cycle->months()));

        $this->cp()->executeStatement(
            'UPDATE subscriptions
             SET status = ?, current_period_start = ?, current_period_end = ?, updated_at = NOW()
             WHERE id = ?',
            ['active', $now->format('Y-m-d H:i:s'), $periodEnd->format('Y-m-d H:i:s'), $subscriptionId],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    public function renew(int $subscriptionId): void
    {
        $cycle = $this->resolveBillingCycle($subscriptionId);
        $now = new \DateTimeImmutable();
        $periodEnd = $now->modify(sprintf('+%d months', $cycle->months()));

        $this->cp()->executeStatement(
            'UPDATE subscriptions
             SET status = ?, current_period_start = ?, current_period_end = ?, updated_at = NOW()
             WHERE id = ?',
            ['active', $now->format('Y-m-d H:i:s'), $periodEnd->format('Y-m-d H:i:s'), $subscriptionId],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    public function markPastDue(int $subscriptionId): void
    {
        $this->updateStatus($subscriptionId, 'past_due');
    }

    public function cancel(int $subscriptionId): void
    {
        $this->cp()->executeStatement(
            'UPDATE subscriptions SET status = ?, cancelled_at = NOW(), updated_at = NOW() WHERE id = ?',
            ['cancelled', $subscriptionId],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    public function suspend(int $subscriptionId): void
    {
        $this->updateStatus($subscriptionId, 'suspended');
    }

    public function linkTenant(int $subscriptionId, int $tenantId): void
    {
        $this->cp()->executeStatement(
            'UPDATE subscriptions SET tenant_id = ?, updated_at = NOW() WHERE id = ?',
            [$tenantId, $subscriptionId],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByGatewaySubscriptionId(string $gateway, string $gatewaySubscriptionId): ?array
    {
        $row = $this->cp()->fetchAssociative(
            'SELECT * FROM subscriptions WHERE gateway = ? AND gateway_subscription_id = ? LIMIT 1',
            [$gateway, $gatewaySubscriptionId],
            [ParameterType::STRING, ParameterType::STRING],
        );

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $subscriptionId): ?array
    {
        $row = $this->cp()->fetchAssociative(
            'SELECT * FROM subscriptions WHERE id = ?',
            [$subscriptionId],
            [ParameterType::INTEGER],
        );

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByTenant(int $tenantId): ?array
    {
        $row = $this->cp()->fetchAssociative(
            "SELECT * FROM subscriptions
             WHERE tenant_id = ? AND status IN ('active', 'past_due')
             ORDER BY id DESC LIMIT 1",
            [$tenantId],
            [ParameterType::INTEGER],
        );

        return $row === false ? null : $row;
    }

    /**
     * Find subscriptions past their grace period that need to be suspended.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findExpiredGracePeriod(): array
    {
        return $this->cp()->fetchAllAssociative(
            "SELECT s.*, t.name AS tenant_name
             FROM subscriptions s
             LEFT JOIN tenants t ON t.id = s.tenant_id
             WHERE s.status = 'past_due'
               AND s.current_period_end IS NOT NULL
               AND DATE_ADD(s.current_period_end, INTERVAL s.grace_period_days DAY) < NOW()"
        );
    }

    /**
     * Find subscriptions approaching grace period expiration (for notifications).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findExpiringGracePeriod(int $daysUntilExpiry = 2): array
    {
        return $this->cp()->fetchAllAssociative(
            "SELECT s.*, a.email AS account_email, t.name AS tenant_name
             FROM subscriptions s
             INNER JOIN accounts a ON a.id = s.account_id
             LEFT JOIN tenants t ON t.id = s.tenant_id
             WHERE s.status = 'past_due'
               AND s.current_period_end IS NOT NULL
               AND DATE_ADD(s.current_period_end, INTERVAL s.grace_period_days DAY)
                   BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)",
            [$daysUntilExpiry],
            [ParameterType::INTEGER],
        );
    }

    public function logPaymentEvent(PaymentEvent $event, ?int $subscriptionId, ?int $tenantId): void
    {
        try {
            $this->cp()->executeStatement(
                'INSERT IGNORE INTO payment_events
                 (subscription_id, tenant_id, gateway, event_type, gateway_event_id, payload, processed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $subscriptionId,
                    $tenantId,
                    $event->gateway,
                    $event->eventType,
                    $event->gatewayEventId,
                    json_encode($event->payload, JSON_THROW_ON_ERROR),
                ],
                [
                    $subscriptionId !== null ? ParameterType::INTEGER : ParameterType::NULL,
                    $tenantId !== null ? ParameterType::INTEGER : ParameterType::NULL,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to log payment event', [
                'gateway' => $event->gateway,
                'event_id' => $event->gatewayEventId,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check if an event was already processed (idempotency).
     */
    public function isEventProcessed(string $gateway, string $gatewayEventId): bool
    {
        $exists = $this->cp()->fetchOne(
            'SELECT 1 FROM payment_events
             WHERE gateway = ? AND gateway_event_id = ?
               AND processed_at IS NOT NULL
             LIMIT 1',
            [$gateway, $gatewayEventId],
            [ParameterType::STRING, ParameterType::STRING],
        );

        return $exists !== false;
    }

    private function resolveBillingCycle(int $subscriptionId): BillingCycle
    {
        $value = $this->cp()->fetchOne(
            'SELECT billing_cycle FROM subscriptions WHERE id = ?',
            [$subscriptionId],
            [ParameterType::INTEGER],
        );

        if ($value === false || !\is_string($value)) {
            return BillingCycle::Monthly;
        }

        return BillingCycle::tryFrom($value) ?? BillingCycle::Monthly;
    }

    private function updateStatus(int $subscriptionId, string $status): void
    {
        $this->cp()->executeStatement(
            'UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?',
            [$status, $subscriptionId],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
