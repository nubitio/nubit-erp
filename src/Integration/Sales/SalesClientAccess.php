<?php

declare(strict_types=1);

namespace App\Integration\Sales;

use App\Integration\Security\ApiClientUser;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class SalesClientAccess
{
    public const string READ_SCOPE = 'sales:read';
    public const string WRITE_SCOPE = 'sales:write';

    public function __construct(
        private Connection $connection,
        private TenantContext $tenantContext,
    ) {}

    public function requireBranch(ApiClientUser $client, string $requestedBranch): string
    {
        if (
            $client->getTenant() !== $this->tenantContext->getTenantName()
            || !in_array(self::WRITE_SCOPE, $client->getScopes(), strict: true)
        ) {
            throw new AccessDeniedException();
        }

        $branchId = $this->connection->fetchOne(<<<'SQL'
            SELECT b.local_id FROM integration_api_client c
            INNER JOIN local b ON b.local_id = c.sales_branch_id
            WHERE c.id = ? AND b.is_active = 1
            SQL, [$client->getClientId()]);
        if (!is_string($branchId) || $branchId !== $requestedBranch) {
            throw new AccessDeniedException();
        }

        return $branchId;
    }

    /** @return array<string, mixed>|null */
    public function findSale(ApiClientUser $client, string $id): ?array
    {
        if (
            $client->getTenant() !== $this->tenantContext->getTenantName()
            || !in_array(self::READ_SCOPE, $client->getScopes(), strict: true)
        ) {
            throw new AccessDeniedException();
        }

        $sale = $this->connection->fetchAssociative(<<<'SQL'
            SELECT s.id AS sale_id, s.status, s.currency, s.grand_total AS total, s.balance_due
            FROM sales s
            INNER JOIN integration_api_client c ON c.id = s.integration_client_id AND c.sales_branch_id = s.branch_id
            INNER JOIN local b ON b.local_id = c.sales_branch_id AND b.is_active = 1
            WHERE s.id = ? AND c.id = ?
            SQL, [$id, $client->getClientId()]);

        return $sale === false ? null : $sale;
    }
}
