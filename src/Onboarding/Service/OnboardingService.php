<?php

declare(strict_types=1);

namespace App\Onboarding\Service;

use App\Onboarding\Message\ProvisionTenantMessage;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class OnboardingService
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private SubdomainValidator $subdomainValidator,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array{token:string,provision_id:int}
     */
    public function startProvisioning(
        int $accountId,
        string $tenantName,
        string $subdomain,
        string $plan,
        string $adminPassword,
    ): array {
        $tenantName = trim($tenantName);
        $subdomain = mb_strtolower(trim($subdomain));
        $plan = mb_strtolower(trim($plan));

        if ($tenantName === '') {
            throw new InvalidArgumentException('El nombre de empresa es obligatorio.');
        }

        if ($adminPassword === '' || mb_strlen($adminPassword) < 8) {
            throw new InvalidArgumentException('La password del admin debe tener al menos 8 caracteres.');
        }

        if ($plan !== 'free') {
            throw new InvalidArgumentException('Por ahora solo esta habilitado el plan free en este flujo.');
        }

        $check = $this->subdomainValidator->check($subdomain);
        if (!$check['available']) {
            throw new InvalidArgumentException($check['reason']);
        }

        $token = bin2hex(random_bytes(32));
        $domain = $check['domain'];

        $conn = $this->cp();
        $conn->insert('tenant_provisions', [
            'tenant_id' => null,
            'account_id' => $accountId,
            'tenant_name' => $tenantName,
            'domain' => $domain,
            'plan' => $plan,
            'admin_user' => 'admin',
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
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
        ]);

        $provisionId = (int) $conn->lastInsertId();

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

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
