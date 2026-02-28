<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228170000_phase5_offline_hardening extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sales V2 phase 5: standardize offline metadata and idempotency across write-heavy tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE sales
    ADD version_no INT NOT NULL DEFAULT 1,
    ADD last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX idx_sales_sync_status_synced (sync_status, synced_at),
    ADD INDEX idx_sales_device_synced (device_id, synced_at),
    ADD UNIQUE INDEX uq_sales_device_local_uuid (device_id, local_uuid)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE sale_items
    ADD local_uuid CHAR(36) DEFAULT NULL,
    ADD idempotency_key CHAR(36) DEFAULT NULL,
    ADD sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    ADD synced_at DATETIME DEFAULT NULL,
    ADD device_id VARCHAR(100) DEFAULT NULL,
    ADD version_no INT NOT NULL DEFAULT 1,
    ADD last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE INDEX uq_sale_items_idempotency_key (idempotency_key),
    ADD UNIQUE INDEX uq_sale_items_device_local_uuid (device_id, local_uuid),
    ADD INDEX idx_sale_items_sync_status_synced (sync_status, synced_at)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE sale_payments
    ADD local_uuid CHAR(36) DEFAULT NULL,
    ADD idempotency_key CHAR(36) DEFAULT NULL,
    ADD sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    ADD synced_at DATETIME DEFAULT NULL,
    ADD device_id VARCHAR(100) DEFAULT NULL,
    ADD version_no INT NOT NULL DEFAULT 1,
    ADD last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE INDEX uq_sale_payments_idempotency_key (idempotency_key),
    ADD UNIQUE INDEX uq_sale_payments_device_local_uuid (device_id, local_uuid),
    ADD INDEX idx_sale_payments_sync_status_synced (sync_status, synced_at)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE returns
    ADD device_id VARCHAR(100) DEFAULT NULL,
    ADD version_no INT NOT NULL DEFAULT 1,
    ADD last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX idx_returns_sync_status_synced (sync_status, synced_at),
    ADD UNIQUE INDEX uq_returns_device_local_uuid (device_id, local_uuid)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE stock_movements
    ADD device_id VARCHAR(100) DEFAULT NULL,
    ADD version_no INT NOT NULL DEFAULT 1,
    ADD last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX idx_stock_movements_sync_status_synced (sync_status, synced_at),
    ADD UNIQUE INDEX uq_stock_movements_device_local_uuid (device_id, local_uuid)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE cash_movements
    ADD local_uuid CHAR(36) DEFAULT NULL,
    ADD idempotency_key CHAR(36) DEFAULT NULL,
    ADD sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    ADD synced_at DATETIME DEFAULT NULL,
    ADD device_id VARCHAR(100) DEFAULT NULL,
    ADD version_no INT NOT NULL DEFAULT 1,
    ADD last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE INDEX uq_cash_movements_idempotency_key (idempotency_key),
    ADD UNIQUE INDEX uq_cash_movements_device_local_uuid (device_id, local_uuid),
    ADD INDEX idx_cash_movements_sync_status_synced (sync_status, synced_at)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_movements DROP INDEX uq_cash_movements_idempotency_key, DROP INDEX uq_cash_movements_device_local_uuid, DROP INDEX idx_cash_movements_sync_status_synced');
        $this->addSql('ALTER TABLE cash_movements DROP COLUMN local_uuid, DROP COLUMN idempotency_key, DROP COLUMN sync_status, DROP COLUMN synced_at, DROP COLUMN device_id, DROP COLUMN version_no, DROP COLUMN last_modified_at');

        $this->addSql('ALTER TABLE stock_movements DROP INDEX uq_stock_movements_device_local_uuid, DROP INDEX idx_stock_movements_sync_status_synced');
        $this->addSql('ALTER TABLE stock_movements DROP COLUMN device_id, DROP COLUMN version_no, DROP COLUMN last_modified_at');

        $this->addSql('ALTER TABLE returns DROP INDEX uq_returns_device_local_uuid, DROP INDEX idx_returns_sync_status_synced');
        $this->addSql('ALTER TABLE returns DROP COLUMN device_id, DROP COLUMN version_no, DROP COLUMN last_modified_at');

        $this->addSql('ALTER TABLE sale_payments DROP INDEX uq_sale_payments_idempotency_key, DROP INDEX uq_sale_payments_device_local_uuid, DROP INDEX idx_sale_payments_sync_status_synced');
        $this->addSql('ALTER TABLE sale_payments DROP COLUMN local_uuid, DROP COLUMN idempotency_key, DROP COLUMN sync_status, DROP COLUMN synced_at, DROP COLUMN device_id, DROP COLUMN version_no, DROP COLUMN last_modified_at');

        $this->addSql('ALTER TABLE sale_items DROP INDEX uq_sale_items_idempotency_key, DROP INDEX uq_sale_items_device_local_uuid, DROP INDEX idx_sale_items_sync_status_synced');
        $this->addSql('ALTER TABLE sale_items DROP COLUMN local_uuid, DROP COLUMN idempotency_key, DROP COLUMN sync_status, DROP COLUMN synced_at, DROP COLUMN device_id, DROP COLUMN version_no, DROP COLUMN last_modified_at');

        $this->addSql('ALTER TABLE sales DROP INDEX uq_sales_device_local_uuid, DROP INDEX idx_sales_sync_status_synced, DROP INDEX idx_sales_device_synced');
        $this->addSql('ALTER TABLE sales DROP COLUMN version_no, DROP COLUMN last_modified_at');
    }
}
