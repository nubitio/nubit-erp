<?php

declare(strict_types=1);

namespace App\Onboarding\Service;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Payment\PaymentGatewayInterface;
use App\Onboarding\Payment\SubscriptionManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Manages subscription changes (upgrade, downgrade, gateway switch) for existing tenants.
 */
final readonly class SubscriptionService
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways;

    /**
     * @param iterable<PaymentGatewayInterface> $gatewayIterator
     */
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private SubscriptionManager $subscriptionManager,
        private LoggerInterface $logger,
        #[TaggedIterator('app.payment_gateway')]
        iterable $gatewayIterator,
    ) {
        $map = [];
        foreach ($gatewayIterator as $gateway) {
            $map[$gateway->getName()] = $gateway;
        }
        $this->gateways = $map;
    }

    /**
     * Change plan for an existing tenant (upgrade/downgrade).
     *
     * Cancels the current gateway subscription and creates a new one with the new plan.
     *
     * @return array{approval_url:string}
     */
    public function changePlan(
        int $tenantId,
        int $accountId,
        string $newPlan,
        BillingCycle $billingCycle,
        string $gateway,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        $paymentGateway = $this->gateways[$gateway] ?? null;
        if ($paymentGateway === null) {
            throw new InvalidArgumentException('Pasarela de pago no disponible: ' . $gateway);
        }

        $current = $this->subscriptionManager->findActiveByTenant($tenantId);
        if ($current !== null) {
            $currentGatewaySubId = (string) ($current['gateway_subscription_id'] ?? '');
            $currentGateway = (string) ($current['gateway'] ?? '');

            if ($currentGatewaySubId !== '' && isset($this->gateways[$currentGateway])) {
                try {
                    $this->gateways[$currentGateway]->cancelSubscription($currentGatewaySubId);
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to cancel old subscription in gateway', [
                        'subscription_id' => $current['id'],
                        'exception' => $e,
                    ]);
                }
            }

            $this->subscriptionManager->cancel((int) $current['id']);
        }

        $email = $this->getAccountEmail($accountId);

        $result = $paymentGateway->createSubscription(
            $newPlan,
            $billingCycle,
            $email,
            $returnUrl,
            $cancelUrl,
        );

        $this->subscriptionManager->create(
            tenantId: $tenantId,
            accountId: $accountId,
            plan: $newPlan,
            billingCycle: $billingCycle,
            gateway: $gateway,
            gatewaySubscriptionId: $result->gatewaySubscriptionId,
            gatewayCustomerId: $result->gatewayCustomerId,
        );

        $this->cp()->executeStatement(
            'UPDATE tenants SET plan = ?, updated_at = NOW() WHERE id = ?',
            [$newPlan, $tenantId],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        return ['approval_url' => $result->approvalUrl];
    }

    /**
     * Switch gateway for an existing subscription (keeping same plan).
     *
     * @return array{approval_url:string}
     */
    public function changeGateway(
        int $tenantId,
        int $accountId,
        string $newGateway,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        $current = $this->subscriptionManager->findActiveByTenant($tenantId);
        if ($current === null) {
            throw new InvalidArgumentException('No hay suscripcion activa para este tenant.');
        }

        $plan = (string) $current['plan'];
        $billingCycle = BillingCycle::tryFrom(
            (string) ($current['billing_cycle'] ?? 'monthly')
        ) ?? BillingCycle::Monthly;

        return $this->changePlan($tenantId, $accountId, $plan, $billingCycle, $newGateway, $returnUrl, $cancelUrl);
    }

    /**
     * Cancel the active subscription for a tenant.
     */
    public function cancelSubscription(int $tenantId): void
    {
        $current = $this->subscriptionManager->findActiveByTenant($tenantId);
        if ($current === null) {
            return;
        }

        $gatewaySubId = (string) ($current['gateway_subscription_id'] ?? '');
        $gatewayName = (string) ($current['gateway'] ?? '');

        if ($gatewaySubId !== '' && isset($this->gateways[$gatewayName])) {
            try {
                $this->gateways[$gatewayName]->cancelSubscription($gatewaySubId);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to cancel subscription in gateway', [
                    'subscription_id' => $current['id'],
                    'exception' => $e,
                ]);
            }
        }

        $this->subscriptionManager->cancel((int) $current['id']);
    }

    /**
     * Get subscription details for a tenant owned by an account.
     *
     * @return array<string, mixed>|null
     */
    public function getSubscriptionForTenant(int $tenantId, int $accountId): ?array
    {
        $row = $this->cp()->fetchAssociative(
            "SELECT s.*
             FROM subscriptions s
             INNER JOIN account_tenants at ON at.tenant_id = s.tenant_id AND at.account_id = ?
             WHERE s.tenant_id = ? AND s.status IN ('active', 'past_due', 'pending')
             ORDER BY s.id DESC
             LIMIT 1",
            [$accountId, $tenantId],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );

        return $row === false ? null : $row;
    }

    private function getAccountEmail(int $accountId): string
    {
        $email = $this->cp()->fetchOne(
            'SELECT email FROM accounts WHERE id = ?',
            [$accountId],
            [ParameterType::INTEGER],
        );

        if ($email === false || !\is_string($email)) {
            throw new InvalidArgumentException('Cuenta no encontrada.');
        }

        return $email;
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
