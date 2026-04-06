<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Reads and writes provision-tracking rows in the control_plane `tenant_provisions` table.
 *
 * The table is not mapped as a Doctrine entity (it is a lightweight audit log managed
 * by the admin layer), so raw DBAL is the correct approach here.
 */
readonly class TenantProvisionRepository
{
    private Connection $cp;

    public function __construct(ConnectionRegistry $connectionRegistry)
    {
        /** @var Connection $cp */
        $cp = $connectionRegistry->getConnection('control_plane');
        $this->cp = $cp;
    }

    /**
     * Inserts a "dispatched" provision row and returns its auto-increment id.
     */
    public function recordDispatch(
        int $tenantId,
        string $tenantName,
        string $domain,
        string $adminUser,
    ): int {
        $sql = 'INSERT INTO tenant_provisions'
            . ' (tenant_id, tenant_name, domain, admin_user, status, created_at)'
            . ' VALUES (?, ?, ?, ?, \'dispatched\', NOW())';

        $this->cp->executeStatement(
            $sql,
            [$tenantId, $tenantName, $domain, $adminUser],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ]
        );

        return (int) $this->cp->lastInsertId();
    }

    /**
     * Marks a provision row as failed and records the truncated error message.
     */
    public function markFailed(int $provisionId, string $error): void
    {
        $this->cp->executeStatement(
            'UPDATE tenant_provisions SET status = ?, error_message = ?, completed_at = NOW() WHERE id = ?',
            ['failed', mb_substr($error, 0, 65535), $provisionId],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
        );
    }

    /**
     * Returns the last 20 provision rows for a tenant, newest first.
     *
     * Returns an empty array if the table does not yet exist (e.g. fresh install).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByTenantId(int $tenantId): array
    {
        if (!$this->cp->createSchemaManager()->tablesExist(['tenant_provisions'])) {
            return [];
        }

        return $this->cp->fetchAllAssociative(
            'SELECT * FROM tenant_provisions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?',
            [$tenantId, 20],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
    }
}
