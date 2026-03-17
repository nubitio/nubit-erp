<?php

declare(strict_types=1);

namespace App\Onboarding\Service;

use App\Onboarding\Message\ProvisionTenantMessage;
use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Payment\PaymentGatewayInterface;
use App\Onboarding\Payment\SubscriptionManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class OnboardingService
{
    private const array PAID_PLANS = ['basic', 'pro'];

    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways;

    /**
     * @param iterable<PaymentGatewayInterface> $gatewayIterator
     */
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private SubdomainValidator $subdomainValidator,
        private MessageBusInterface $messageBus,
        private SubscriptionManager $subscriptionManager,
        #[AutowireIterator('app.payment_gateway')]
        iterable $gatewayIterator,
    ) {
        $map = [];
        foreach ($gatewayIterator as $gateway) {
            $map[$gateway->getName()] = $gateway;
        }
        $this->gateways = $map;
    }

    /**
     * Start provisioning for free plans (dispatches immediately).
     *
     * @return array{token:string,provision_id:int,requires_payment:false}
     */
    public function startFreeProvisioning(
        int $accountId,
        string $tenantName,
        string $subdomain,
        string $adminPassword,
    ): array {
        $this->validateCommonFields($tenantName, $subdomain, $adminPassword);

        $check = $this->subdomainValidator->check($subdomain);
        if (!$check['available']) {
            throw new InvalidArgumentException($check['reason']);
        }

        $token = bin2hex(random_bytes(32));
        $domain = $check['domain'];
        $provisionId = $this->createProvisionRecord($accountId, $tenantName, $domain, 'free', $token);

        $this->messageBus->dispatch(new ProvisionTenantMessage(
            provisionId: $provisionId,
            accountId: $accountId,
            tenantName: $tenantName,
            subdomain: $check['normalized'],
            domain: $domain,
            adminPassword: $adminPassword,
        ));

        return [
            'token' => $token,
            'provision_id' => $provisionId,
            'requires_payment' => false,
        ];
    }

    /**
     * Start provisioning for paid plans (creates subscription, returns gateway approval URL).
     *
     * @return array{token:string,provision_id:int,requires_payment:true,approval_url:string,subscription_id:int}
     */
    public function startPaidProvisioning(
        int $accountId,
        string $tenantName,
        string $subdomain,
        string $plan,
        string $gateway,
        BillingCycle $billingCycle,
        string $adminPassword,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        if (!\in_array($plan, self::PAID_PLANS, true)) {
            throw new InvalidArgumentException('Plan invalido para pago: ' . $plan);
        }

        $paymentGateway = $this->gateways[$gateway] ?? null;
        if ($paymentGateway === null) {
            throw new InvalidArgumentException('Pasarela de pago no disponible: ' . $gateway);
        }

        $this->validateCommonFields($tenantName, $subdomain, $adminPassword);

        $check = $this->subdomainValidator->check($subdomain);
        if (!$check['available']) {
            throw new InvalidArgumentException($check['reason']);
        }

        $token = bin2hex(random_bytes(32));
        $domain = $check['domain'];
        $provisionId = $this->createProvisionRecord($accountId, $tenantName, $domain, $plan, $token, $adminPassword);

        $accountEmail = $this->getAccountEmail($accountId);

        $subscriptionResult = $paymentGateway->createSubscription(
            $plan,
            $billingCycle,
            $accountEmail,
            $returnUrl,
            $cancelUrl,
        );

        $subResult = $this->subscriptionManager->createPending(
            accountId: $accountId,
            provisionId: $provisionId,
            plan: $plan,
            billingCycle: $billingCycle,
            gateway: $gateway,
            gatewaySubscriptionId: $subscriptionResult->gatewaySubscriptionId,
            gatewayCustomerId: $subscriptionResult->gatewayCustomerId,
        );

        return [
            'token' => $token,
            'provision_id' => $provisionId,
            'requires_payment' => true,
            'approval_url' => $subscriptionResult->approvalUrl,
            'subscription_id' => $subResult['subscription_id'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProvisionStatus(string $token, int $accountId): ?array
    {
        $row = $this->cp()->fetchAssociative(
            'SELECT p.id, p.account_id, p.tenant_id, p.tenant_name, p.domain, p.plan, p.status, p.error_message,
                    p.created_at, p.started_at, p.completed_at,
                    t.name AS tenant_slug,
                    td.domain AS primary_domain
             FROM tenant_provisions p
             LEFT JOIN tenants t ON t.id = p.tenant_id
             LEFT JOIN tenant_domains td ON td.tenant_id = p.tenant_id AND td.is_primary = 1
             WHERE p.public_token = ? AND p.account_id = ?
             LIMIT 1',
            [$token, $accountId],
            [ParameterType::STRING, ParameterType::INTEGER]
        );

        if ($row === false) {
            return null;
        }

        return $row;
    }

    public static function isPaidPlan(string $plan): bool
    {
        return \in_array(mb_strtolower(trim($plan)), self::PAID_PLANS, true);
    }

    private function validateCommonFields(string &$tenantName, string &$subdomain, string $adminPassword): void
    {
        $tenantName = trim($tenantName);
        $subdomain = mb_strtolower(trim($subdomain));

        if ($tenantName === '') {
            throw new InvalidArgumentException('El nombre de empresa es obligatorio.');
        }

        if ($adminPassword === '' || mb_strlen($adminPassword) < 8) {
            throw new InvalidArgumentException('La password del admin debe tener al menos 8 caracteres.');
        }
    }

    private function createProvisionRecord(
        int $accountId,
        string $tenantName,
        string $domain,
        string $plan,
        string $token,
        string $adminPassword = '',
    ): int {
        $conn = $this->cp();
        $conn->insert('tenant_provisions', [
            'tenant_id' => null,
            'account_id' => $accountId,
            'tenant_name' => $tenantName,
            'domain' => $domain,
            'plan' => $plan,
            'admin_user' => 'admin',
            'admin_password' => $adminPassword !== '' ? $adminPassword : null,
            'target_container' => '',
            'status' => 'dispatched',
            'public_token' => $token,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            ParameterType::NULL,
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            $adminPassword !== '' ? ParameterType::STRING : ParameterType::NULL,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
        ]);

        return (int) $conn->lastInsertId();
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
