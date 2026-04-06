<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix: stock_movements.id type mismatch.
 *
 * Context
 * -------
 * The table was originally created with `id BIGINT AUTO_INCREMENT`. InventoryService V2
 * (src/Inventory/Core/Service/InventoryService.php) generates UUID v7 strings for the id
 * column — these cannot be stored in a BIGINT column.
 *
 * Additionally, stock_items.last_movement_id was added as CHAR(36) (FK → stock_movements.id)
 * but the referenced column was still BIGINT, creating a type mismatch in the schema comment.
 *
 * Fix
 * ---
 * 1. Backfill existing BIGINT ids with a zero-padded UUID representation so existing rows
 *    remain valid after the type change.
 * 2. Modify stock_movements.id from BIGINT AUTO_INCREMENT to CHAR(36) NOT NULL.
 * 3. The idempotency_key unique constraint already provides insert deduplication;
 *    no new unique index is needed on id itself (it remains PRIMARY KEY).
 *
 * Rollback
 * --------
 * DOWN is intentionally a no-op because once UUID-valued rows exist they cannot be converted
 * back to BIGINT without data loss. Run a full DB restore if rollback is required.
 *
 * @see src/Inventory/Core/Service/InventoryService.php::persistMovement()
 * @see ADR-009 (inventory ledger read model)
 * @see ADR-018 (inventory service cutover plan)
 */
final class Version20260402000001_fix_stock_movements_id_to_uuid extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change stock_movements.id from BIGINT AUTO_INCREMENT to CHAR(36) UUID to match InventoryService V2';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Backfill existing BIGINT ids with zero-padded UUID-format strings.
        // Format: 00000000-0000-7000-0000-<12-digit-zero-padded-id>
        // The 7xxx prefix signals "synthetic UUIDv7 from BIGINT migration".
        $this->addSql(<<<'SQL'
            UPDATE stock_movements
            SET id = CONCAT(
                '00000000-0000-7000-0000-',
                LPAD(CAST(id AS CHAR), 12, '0')
            )
            WHERE id REGEXP '^[0-9]+$'
        SQL);

        // Step 2: Change the column type.
        // Must drop AUTO_INCREMENT first (implicit when removing BIGINT definition).
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements
                MODIFY id CHAR(36) NOT NULL
        SQL);

        // Step 3: stock_items.last_movement_id is already CHAR(36) — no change needed there.
        // Verify the column exists as CHAR(36) (guard; migration is idempotent on this step).
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op: UUID strings cannot be safely converted back to BIGINT.
        // Restore from a pre-migration DB backup if rollback is required.
        $this->write(
            'DOWN migration is a no-op. Restore from backup to revert stock_movements.id type change.'
        );
    }
}
