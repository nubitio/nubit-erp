<?php

declare(strict_types=1);

namespace App\Onboarding\MessageHandler;

use App\Onboarding\Message\ProvisionTenantMessage;
use App\Onboarding\Service\TenantProvisioningService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProvisionTenantHandler
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantProvisioningService $tenantProvisioningService,
        private LoggerInterface $logger,
        #[Autowire(env: 'ONBOARDING_TENANT_DB_PASS')]
        private string $onboardingTenantDbPass,
        #[Autowire(env: 'DATABASE_URL')]
        private string $databaseUrl,
    ) {
    }

    public function __invoke(ProvisionTenantMessage $message): void
    {
        $tenantDbPassword = $this->resolveTenantDbPassword();
        if ($tenantDbPassword === '') {
            $this->markProvisionFailed(
                $message->provisionId,
                'Missing tenant DB password. Configure ONBOARDING_TENANT_DB_PASS or DATABASE_URL with password.'
            );

            return;
        }

        $result = $this->tenantProvisioningService->run(
            provisionId: $message->provisionId,
            tenantName: $message->tenantName,
            subdomain: $message->subdomain,
            domain: $message->domain,
            adminPassword: $message->adminPassword,
            tenantDbPassword: $tenantDbPassword,
        );

        if (!$result['successful']) {
            $combinedOutput = trim($result['output']);
            if ($combinedOutput === '') {
                $combinedOutput = 'Provisioning command failed without output';
            }

            $this->logger->error('Onboarding provisioning command failed', [
                'provision_id' => $message->provisionId,
                'provision_tenant' => $message->subdomain,
                'exit_code' => $result['exit_code'],
                'command_output' => mb_substr($combinedOutput, 0, 8000),
            ]);

            $this->markProvisionFailed(
                $message->provisionId,
                'Provisioning command failed: ' . mb_substr($combinedOutput, 0, 2000)
            );

            return;
        }

        $tenantId = (int) $this->cp()->fetchOne('SELECT id FROM tenants WHERE name = ?', [$message->subdomain]);
        if ($tenantId <= 0) {
            $this->markProvisionFailed($message->provisionId, 'Tenant record was not created');

            return;
        }

        $this->cp()->executeStatement(
            'UPDATE tenant_provisions SET tenant_id = ? WHERE id = ?',
            [$tenantId, $message->provisionId],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        $this->cp()->executeStatement(
            'INSERT INTO account_tenants (account_id, tenant_id, role, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE role = VALUES(role)',
            [$message->accountId, $tenantId, 'owner'],
            [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING]
        );
    }

    private function markProvisionFailed(int $provisionId, string $error): void
    {
        $this->cp()->executeStatement(
            'UPDATE tenant_provisions
             SET status = ?, error_message = ?, completed_at = NOW()
             WHERE id = ?',
            ['failed', mb_substr($error, 0, 65535), $provisionId],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
        );
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }

    private function resolveTenantDbPassword(): string
    {
        $explicit = trim($this->onboardingTenantDbPass);
        if ($explicit !== '') {
            return $explicit;
        }

        $databaseUrl = trim($this->databaseUrl);
        if ($databaseUrl === '') {
            return '';
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            return '';
        }

        return isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '';
    }
}
