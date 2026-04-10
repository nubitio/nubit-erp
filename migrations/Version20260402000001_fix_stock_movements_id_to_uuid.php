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
 * Additionally, stock_items.last_movement_id was added as CHAR(36) (FK → stock_movements.id
 * per comment) but the referenced column was still BIGINT, creating a type mismatch.
 *
 * Fix
 * ---
 * Uses a staging-column approach to avoid the MariaDB BIGINT truncation error that occurs
 * when trying to UPDATE a BIGINT column with a UUID string before the ALTER TABLE.
 *
 * Steps:
 * 1. Add staging column `id_uuid CHAR(36) NULL` (IF NOT EXISTS — idempotent on retry).
 * 2. Backfill `id_uuid` with zero-padded synthetic UUID representations of BIGINT ids.
 *    Format: 00000000-0000-7000-0000-<12-digit-zero-padded-id>
 *    The 7xxx prefix signals "synthetic UUIDv7 from BIGINT migration".
 *    Only rows where `id_uuid` is still NULL are updated (idempotent).
 * 3. Add new column `id_new CHAR(36) NULL` (IF NOT EXISTS) — nullable so no DEFAULT clash.
 * 4. Copy `id_uuid` → `id_new` (UPDATE WHERE id_new IS NULL — idempotent).
 * 5. In one ALTER: drop old PK, make `id_new` NOT NULL, add PK on `id_new`.
 * 6. Drop old `id` BIGINT column and rename `id_new` → `id`.
 * 7. Drop staging column `id_uuid`.
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
        // Step 1: Add staging column (IF NOT EXISTS makes this idempotent on retry).
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements
                ADD COLUMN IF NOT EXISTS `id_uuid` CHAR(36) NULL
        SQL);

        // Step 2: Backfill staging column from BIGINT ids (only NULL rows — idempotent).
        // Writing a UUID string into CHAR(36) is safe; no truncation.
        $this->addSql(<<<'SQL'
            UPDATE stock_movements
            SET id_uuid = CONCAT(
                '00000000-0000-7000-0000-',
                LPAD(CAST(id AS CHAR), 12, '0')
            )
            WHERE id_uuid IS NULL
        SQL);

        // Step 3: Add new id column as nullable CHAR(36) (IF NOT EXISTS — idempotent).
        // Must be nullable here to avoid Duplicate entry '' for PRIMARY KEY across all rows.
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements
                ADD COLUMN IF NOT EXISTS `id_new` CHAR(36) NULL FIRST
        SQL);

        // Step 4: Populate the new id column from the staging column (idempotent).
        $this->addSql(<<<'SQL'
            UPDATE stock_movements
            SET id_new = id_uuid
            WHERE id_new IS NULL
        SQL);

        // Step 5: In a single ALTER:
        //   a. Remove AUTO_INCREMENT from the old `id` BIGINT (required before DROP PRIMARY KEY).
        //   b. Drop the old PRIMARY KEY.
        //   c. Make `id_new` NOT NULL.
        //   d. Add PRIMARY KEY on `id_new`.
        // All four must be in one statement — MariaDB enforces that AUTO_INCREMENT columns
        // must always be part of a key; dropping PK while AUTO_INCREMENT exists is only
        // allowed when the AUTO_INCREMENT is simultaneously removed.
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements
                MODIFY COLUMN `id` BIGINT(20) NOT NULL,
                DROP PRIMARY KEY,
                MODIFY COLUMN `id_new` CHAR(36) NOT NULL,
                ADD PRIMARY KEY (`id_new`)
        SQL);

        // Step 6: Drop the old BIGINT id column and rename id_new → id.
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements
                DROP COLUMN `id`,
                CHANGE COLUMN `id_new` `id` CHAR(36) NOT NULL
        SQL);

        // Step 7: Drop the staging column.
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements
                DROP COLUMN IF EXISTS `id_uuid`
        SQL);
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
