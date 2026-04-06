<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Reads payment-event rows from the control_plane `payment_events` table.
 *
 * The table is not mapped as a Doctrine entity (it is written by the payment-gateway
 * webhook layer), so raw DBAL is the correct approach here.
 */
readonly class TenantPaymentEventRepository
{
    private Connection $cp;

    public function __construct(ConnectionRegistry $connectionRegistry)
    {
        /** @var Connection $cp */
        $cp = $connectionRegistry->getConnection('control_plane');
        $this->cp = $cp;
    }

    /**
     * Returns the last 50 payment events for a tenant, newest first.
     *
     * Returns an empty array if the table does not yet exist (e.g. fresh install).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByTenantId(int $tenantId): array
    {
        if (!$this->cp->createSchemaManager()->tablesExist(['payment_events'])) {
            return [];
        }

        return $this->cp->fetchAllAssociative(
            'SELECT * FROM payment_events WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?',
            [$tenantId, 50],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
    }
}
