<?php

declare(strict_types=1);

namespace App\Core\Service;

use RuntimeException;

final readonly class LocalProvisioningDispatcher implements ProvisioningDispatcherInterface
{
    public function __construct(
        private ProvisionCommandRunner $commandRunner,
    ) {
    }

    /**
     * @param array<string, string> $inputs
     */
    public function dispatch(array $inputs): void
    {
        $tenantName = trim((string)($inputs['tenant_name'] ?? ''));
        if ($tenantName === '') {
            throw new RuntimeException('tenant_name is required for local provisioning.');
        }

        $provisionId = (int) trim((string)($inputs['provision_id'] ?? '0'));
        $result = $this->commandRunner->run(new ProvisionCommandRequest(
            tenant: $tenantName,
            domain: trim((string)($inputs['domain'] ?? '')),
            adminUser: trim((string)($inputs['admin_user'] ?? 'admin')),
            provisionId: $provisionId,
            createUser: false,
            setup: false,
        ));

        if ($result['successful']) {
            return;
        }

        $output = trim($result['output']);
        throw new RuntimeException(
            'Local provisioning command failed: ' . ($output !== '' ? $output : 'unknown error')
        );
    }
}
