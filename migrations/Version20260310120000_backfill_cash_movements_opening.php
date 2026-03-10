<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfills OPENING movements in cash_movements for cash_sessions that were
 * created via the legacy path (before Wave 2 / CashRegisterOpenProcessor).
 *
 * The Wave 2 CashRegisterOpenProcessor now inserts an OPENING movement
 * automatically on every new session. Sessions created before that change
 * have no corresponding OPENING row in cash_movements. This migration
 * backfills those rows so the table is consistent across all tenants.
 *
 * Idempotent: the WHERE NOT EXISTS guard prevents duplicate OPENING rows
 * for sessions that were already processed by the live processor.
 */
final class Version20260310120000_backfill_cash_movements_opening extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill OPENING movements in cash_movements for legacy cash_sessions without one';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
INSERT INTO cash_movements (cash_session_id, movement_type, amount, created_by, created_at)
SELECT cs.id, 'OPENING', cs.opening_amount, cs.opened_by, cs.opened_at
FROM cash_sessions cs
WHERE NOT EXISTS (
    SELECT 1
    FROM cash_movements cm
    WHERE cm.cash_session_id = cs.id
      AND cm.movement_type = 'OPENING'
)
SQL);
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op: cannot safely distinguish backfilled from live OPENING movements.
    }
}
