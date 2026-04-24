<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Payment;

use App\ControlPlane\Onboarding\Message\ProvisionTenantMessage;
use App\ControlPlane\Onboarding\Security\SecretCipher;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class WebhookProcessor
{
    /** @var array<string, string> PayPal event type → action mapping */
    private const array PAYPAL_EVENTS = [
        'BILLING.SUBSCRIPTION.ACTIVATED' => 'activate',
        'PAYMENT.SALE.COMPLETED' => 'renew',
        'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => 'past_due',
        'BILLING.SUBSCRIPTION.CANCELLED' => 'cancel',
        'BILLING.SUBSCRIPTION.SUSPENDED' => 'suspend',
        'CUSTOMER.DISPUTE.CREATED' => 'suspend',
    ];

    /** @var array<string, string> MercadoPago event type → action mapping */
    private const array MERCADOPAGO_EVENTS = [
        'subscription_preapproval.authorized' => 'activate',
        'payment.created' => 'payment_check',
        'subscription_preapproval.cancelled' => 'cancel',
        'subscription_preapproval.paused' => 'suspend',
    ];

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly SecretCipher $secretCipher,
    ) {
    }

    public function process(PaymentEvent $event): void
    {
        $action = $this->resolveAction($event);
        if ($action === null) {
            $this->logger->debug('Ignoring unhandled payment event type', [
                'gateway' => $event->gateway,
                'event_type' => $event->eventType,
            ]);

            return;
        }

        $conn = $this->cp();

        $subscription = $this->subscriptionManager->findByGatewaySubscriptionId(
            $event->gateway,
            $event->gatewaySubscriptionId,
        );

        if ($subscription === null) {
            $this->logger->warning('Payment event for unknown subscription', [
                'gateway' => $event->gateway,
                'gateway_subscription_id' => $event->gatewaySubscriptionId,
                'event_type' => $event->eventType,
            ]);

            $this->subscriptionManager->failPaymentEvent($event, null, null, 'Payment event for unknown subscription');

            return;
        }

        $subscriptionId = (int) $subscription['id'];
        $tenantId = (int) ($subscription['tenant_id'] ?? 0);

        $paymentEventId = null;

        try {
            $conn->beginTransaction();
            $paymentEventId = $this->subscriptionManager->beginPaymentEventProcessing(
                $event,
                $subscriptionId,
                $tenantId > 0 ? $tenantId : null,
            );

            if ($paymentEventId === null) {
                $conn->commit();
                $this->logger->info('Skipping already processed payment event', [
                    'gateway' => $event->gateway,
                    'event_id' => $event->gatewayEventId,
                ]);

                return;
            }

            match ($action) {
                'activate' => $this->handleActivate($subscription),
                'renew' => $this->handleRenew($subscriptionId),
                'past_due' => $this->handlePastDue($subscriptionId, $tenantId),
                'cancel' => $this->handleCancel($subscriptionId, $tenantId),
                'suspend' => $this->handleSuspend($subscriptionId, $tenantId),
                'payment_check' => $this->handlePaymentCheck($subscription, $event),
                default => null,
            };

            $this->subscriptionManager->completePaymentEvent($paymentEventId);
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }

            $this->subscriptionManager->failPaymentEvent(
                $event,
                $subscriptionId,
                $tenantId > 0 ? $tenantId : null,
                $e->getMessage(),
            );

            throw $e;
        }
    }

    private function resolveAction(PaymentEvent $event): ?string
    {
        if ($event->gateway === 'paypal') {
            return self::PAYPAL_EVENTS[$event->eventType] ?? null;
        }

        if ($event->gateway === 'mercadopago') {
            return self::MERCADOPAGO_EVENTS[$event->eventType] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function handleActivate(array $subscription): void
    {
        $subscriptionId = (int) $subscription['id'];
        $this->subscriptionManager->activate($subscriptionId);

        $tenantId = (int) ($subscription['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            $this->logger->info('Subscription activated for existing tenant', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        $this->triggerProvisioning($subscription);
    }

    private function handleRenew(int $subscriptionId): void
    {
        $this->subscriptionManager->renew($subscriptionId);
        $this->logger->info('Subscription renewed', ['subscription_id' => $subscriptionId]);
    }

    private function handlePastDue(int $subscriptionId, int $tenantId): void
    {
        $this->subscriptionManager->markPastDue($subscriptionId);
        $this->logger->warning('Subscription payment failed, marked past_due', [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
        ]);
    }

    private function handleCancel(int $subscriptionId, int $tenantId): void
    {
        $this->subscriptionManager->cancel($subscriptionId);

        if ($tenantId > 0) {
            $this->suspendTenant($tenantId);
        }

        $this->logger->info('Subscription cancelled', [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
        ]);
    }

    private function handleSuspend(int $subscriptionId, int $tenantId): void
    {
        $this->subscriptionManager->suspend($subscriptionId);

        if ($tenantId > 0) {
            $this->suspendTenant($tenantId);
        }

        $this->logger->warning('Subscription suspended', [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function handlePaymentCheck(array $subscription, PaymentEvent $event): void
    {
        $paymentStatus = (string) ($event->payload['data']['status'] ?? $event->payload['resource']['status'] ?? '');
        $subscriptionId = (int) $subscription['id'];

        if ($paymentStatus === 'approved' || $paymentStatus === 'completed') {
            $currentStatus = (string) ($subscription['status'] ?? '');
            if ($currentStatus === 'past_due') {
                $this->subscriptionManager->renew($subscriptionId);
            } elseif ($currentStatus === 'pending') {
                $this->handleActivate($subscription);
            }
        } elseif (\in_array($paymentStatus, ['rejected', 'cancelled', 'refunded'], true)) {
            $this->handlePastDue($subscriptionId, (int) ($subscription['tenant_id'] ?? 0));
        }
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function triggerProvisioning(array $subscription): void
    {
        $conn = $this->cp();
        $provisionId = (int) ($subscription['provision_id'] ?? 0);
        if ($provisionId > 0) {
            $provision = $conn->fetchAssociative(
                "SELECT * FROM tenant_provisions
                 WHERE id = ? AND account_id = ? AND status = 'dispatched'
                 LIMIT 1",
                [$provisionId, (int) $subscription['account_id']],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            );
        } else {
            // Backward compatibility for older subscriptions created before provision_id existed.
            $this->logger->warning('Subscription without provision_id; using legacy account+plan lookup', [
                'subscription_id' => $subscription['id'] ?? null,
                'account_id' => $subscription['account_id'] ?? null,
            ]);
            $provision = $conn->fetchAssociative(
                "SELECT * FROM tenant_provisions
                 WHERE account_id = ? AND plan = ? AND status = 'dispatched'
                 ORDER BY id DESC LIMIT 1",
                [(int) $subscription['account_id'], (string) $subscription['plan']],
                [ParameterType::INTEGER, ParameterType::STRING],
            );
        }

        if ($provision === false) {
            $this->logger->warning('No dispatched provision found for subscription provision_id', [
                'subscription_id' => $subscription['id'],
                'account_id' => $subscription['account_id'],
                'provision_id' => $provisionId,
            ]);

            return;
        }

        $this->messageBus->dispatch(new ProvisionTenantMessage(
            provisionId: (int) $provision['id'],
            accountId: (int) $provision['account_id'],
            tenantName: (string) $provision['tenant_name'],
            subdomain: $this->extractSubdomain((string) $provision['domain']),
            domain: (string) $provision['domain'],
            adminPasswordSecret: $this->protectAdminPassword((string) ($provision['admin_password'] ?? '')),
        ));

        // Remove the raw password once the provisioning job is queued.
        $conn->executeStatement(
            'UPDATE tenant_provisions SET admin_password = NULL WHERE id = ?',
            [(int) $provision['id']],
            [ParameterType::INTEGER]
        );
    }

    private function suspendTenant(int $tenantId): void
    {
        $this->cp()->executeStatement(
            "UPDATE tenants SET status = 'suspended', updated_at = NOW() WHERE id = ? AND status = 'active'",
            [$tenantId],
            [ParameterType::INTEGER],
        );
    }

    private function extractSubdomain(string $domain): string
    {
        $parts = explode('.', $domain, 2);

        return $parts[0];
    }

    private function protectAdminPassword(string $storedPassword): string
    {
        if ($storedPassword === '') {
            return '';
        }

        return $this->secretCipher->encrypt($this->secretCipher->decrypt($storedPassword));
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
