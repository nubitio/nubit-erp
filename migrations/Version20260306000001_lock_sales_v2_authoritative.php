<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Locks the SALES_V2_MODE setting to 'v2_authoritative' for every tenant.
 *
 * Wave 2 removed all remaining application-code reads/writes to the legacy
 * `movimiento` table. This migration persists the authoritative mode so it
 * survives DB resets, new-tenant provisioning, and any future config restore.
 *
 * The `config` table has a UNIQUE index on `name` (Version20260209000000),
 * so the INSERT … ON DUPLICATE KEY UPDATE is safe and idempotent.
 */
final class Version20260306000001LockSalesV2Authoritative extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SLICE-002: Lock sales_v2_mode = v2_authoritative — Wave 2 legacy movimiento writes removed.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO config (name, type, value) VALUES ('sales_v2_mode', 'string', 'v2_authoritative')"
            . " ON DUPLICATE KEY UPDATE value = 'v2_authoritative'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE config SET value = 'dual_write' WHERE name = 'sales_v2_mode'");
    }
}
