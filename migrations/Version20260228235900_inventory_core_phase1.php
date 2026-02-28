<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228235900_inventory_core_phase1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventory core phase 1: product typing, unit conversions, stock_items materialized model and tenant stock policy.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('producto')) {
            if (!$this->columnExists('producto', 'product_type')) {
                $this->addSql("ALTER TABLE producto ADD product_type VARCHAR(20) DEFAULT NULL");
                $this->addSql(
                    "UPDATE producto SET product_type = CASE WHEN tipo_id = 1 THEN 'STOCKABLE' ELSE 'SERVICE' END WHERE product_type IS NULL"
                );
                $this->addSql("ALTER TABLE producto MODIFY product_type VARCHAR(20) NOT NULL DEFAULT 'STOCKABLE'");
            }

            if (!$this->columnExists('producto', 'track_inventory')) {
                $this->addSql('ALTER TABLE producto ADD track_inventory TINYINT(1) NOT NULL DEFAULT 1');
                $this->addSql("UPDATE producto SET track_inventory = CASE WHEN product_type IN ('SERVICE', 'DIGITAL') THEN 0 ELSE 1 END");
            }

            if (!$this->columnExists('producto', 'base_unit_id')) {
                $this->addSql('ALTER TABLE producto ADD base_unit_id INT DEFAULT NULL');
                if ($this->columnExists('producto', 'unidad_id')) {
                    $this->addSql('UPDATE producto SET base_unit_id = unidad_id WHERE base_unit_id IS NULL');
                }
                if ($this->tableExists('unidad')) {
                    $this->addSql('ALTER TABLE producto ADD CONSTRAINT FK_PRODUCTO_BASE_UNIT FOREIGN KEY (base_unit_id) REFERENCES unidad (unidad_id)');
                }
            }

            if (!$this->columnExists('producto', 'is_active')) {
                $this->addSql('ALTER TABLE producto ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
            }

            if (!$this->indexExists('producto', 'idx_producto_product_type')) {
                $this->addSql('CREATE INDEX idx_producto_product_type ON producto (product_type)');
            }
            if (!$this->indexExists('producto', 'idx_producto_track_inventory')) {
                $this->addSql('CREATE INDEX idx_producto_track_inventory ON producto (track_inventory)');
            }
            if (!$this->indexExists('producto', 'idx_producto_base_unit')) {
                $this->addSql('CREATE INDEX idx_producto_base_unit ON producto (base_unit_id)');
            }
        }

        if (!$this->tableExists('product_units')) {
            $this->addSql(<<<'SQL'
CREATE TABLE product_units (
    id BIGINT AUTO_INCREMENT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    unit_id INT NOT NULL,
    conversion_to_base DECIMAL(18,6) NOT NULL,
    is_default_sale_unit TINYINT(1) NOT NULL DEFAULT 0,
    purchase_unit_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_product_units_product_unit (product_id, unit_id),
    INDEX idx_product_units_product (product_id),
    INDEX idx_product_units_unit (unit_id),
    INDEX idx_product_units_default_sale (product_id, is_default_sale_unit),
    CONSTRAINT fk_product_units_product FOREIGN KEY (product_id) REFERENCES producto (producto_id) ON DELETE CASCADE,
    CONSTRAINT fk_product_units_unit FOREIGN KEY (unit_id) REFERENCES unidad (unidad_id),
    CONSTRAINT fk_product_units_purchase_unit FOREIGN KEY (purchase_unit_id) REFERENCES unidad (unidad_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        }

        if (!$this->tableExists('stock_items')) {
            $this->addSql(<<<'SQL'
CREATE TABLE stock_items (
    warehouse_id VARCHAR(255) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    qty_on_hand DECIMAL(18,6) NOT NULL DEFAULT 0,
    qty_reserved DECIMAL(18,6) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (warehouse_id, product_id),
    INDEX idx_stock_items_product_updated (product_id, updated_at),
    CONSTRAINT fk_stock_items_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_stock_items_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        }

        if ($this->tableExists('stock_movements')) {
            if (!$this->columnExists('stock_movements', 'qty_delta_base')) {
                $this->addSql('ALTER TABLE stock_movements ADD qty_delta_base DECIMAL(18,6) DEFAULT NULL');
                if ($this->columnExists('stock_movements', 'quantity_delta')) {
                    $this->addSql('UPDATE stock_movements SET qty_delta_base = quantity_delta WHERE qty_delta_base IS NULL');
                }
                $this->addSql('ALTER TABLE stock_movements MODIFY qty_delta_base DECIMAL(18,6) NOT NULL');
            }

            if (!$this->columnExists('stock_movements', 'unit_id')) {
                $this->addSql('ALTER TABLE stock_movements ADD unit_id INT DEFAULT NULL');
                if ($this->tableExists('producto') && $this->columnExists('producto', 'base_unit_id')) {
                    $this->addSql(<<<'SQL'
UPDATE stock_movements sm
JOIN producto p ON p.producto_id = sm.product_id
SET sm.unit_id = p.base_unit_id
WHERE sm.unit_id IS NULL
SQL);
                }
                $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_unit FOREIGN KEY (unit_id) REFERENCES unidad (unidad_id)');
            }

            if (!$this->columnExists('stock_movements', 'qty_original')) {
                $this->addSql('ALTER TABLE stock_movements ADD qty_original DECIMAL(18,6) DEFAULT NULL');
                $this->addSql('UPDATE stock_movements SET qty_original = ABS(qty_delta_base) WHERE qty_original IS NULL');
                $this->addSql('ALTER TABLE stock_movements MODIFY qty_original DECIMAL(18,6) NOT NULL');
            }

            if (!$this->indexExists('stock_movements', 'idx_stock_movements_source_compact')) {
                $this->addSql('CREATE INDEX idx_stock_movements_source_compact ON stock_movements (source_type, source_id)');
            }

            if (!$this->indexExists('stock_movements', 'idx_stock_movements_product_happened')) {
                $this->addSql('CREATE INDEX idx_stock_movements_product_happened ON stock_movements (product_id, occurred_at)');
            }
        }

        if (!$this->tableExists('tenant_inventory_policy')) {
            $this->addSql(<<<'SQL'
CREATE TABLE tenant_inventory_policy (
    id BIGINT AUTO_INCREMENT NOT NULL,
    tenant_id VARCHAR(128) NOT NULL,
    policy VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_tenant_inventory_policy_tenant (tenant_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
            $this->addSql("INSERT INTO tenant_inventory_policy (tenant_id, policy) VALUES ('default', 'STRICT')");
        }

        if ($this->tableExists('product_units') && $this->tableExists('producto') && $this->columnExists('producto', 'base_unit_id')) {
            $this->addSql(<<<'SQL'
INSERT IGNORE INTO product_units (product_id, unit_id, conversion_to_base, is_default_sale_unit, purchase_unit_id)
SELECT p.producto_id, p.base_unit_id, 1.000000, 1, p.base_unit_id
FROM producto p
WHERE p.base_unit_id IS NOT NULL
SQL);
        }

        if ($this->tableExists('stock_items') && $this->tableExists('stock_balances')) {
            $this->addSql(<<<'SQL'
INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at)
SELECT warehouse_id, product_id, on_hand_qty, reserved_qty, NOW()
FROM stock_balances
ON DUPLICATE KEY UPDATE
    qty_on_hand = VALUES(qty_on_hand),
    qty_reserved = VALUES(qty_reserved),
    updated_at = VALUES(updated_at)
SQL);
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('stock_items')) {
            $this->addSql('DROP TABLE stock_items');
        }

        if ($this->tableExists('product_units')) {
            $this->addSql('DROP TABLE product_units');
        }

        if ($this->tableExists('tenant_inventory_policy')) {
            $this->addSql('DROP TABLE tenant_inventory_policy');
        }

        if ($this->tableExists('stock_movements')) {
            if ($this->indexExists('stock_movements', 'idx_stock_movements_product_happened')) {
                $this->addSql('ALTER TABLE stock_movements DROP INDEX idx_stock_movements_product_happened');
            }
            if ($this->indexExists('stock_movements', 'idx_stock_movements_source_compact')) {
                $this->addSql('ALTER TABLE stock_movements DROP INDEX idx_stock_movements_source_compact');
            }
            if ($this->columnExists('stock_movements', 'qty_original')) {
                $this->addSql('ALTER TABLE stock_movements DROP COLUMN qty_original');
            }
            if ($this->columnExists('stock_movements', 'unit_id')) {
                if ($this->foreignKeyExists('stock_movements', 'fk_stock_movements_unit')) {
                    $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY fk_stock_movements_unit');
                }
                $this->addSql('ALTER TABLE stock_movements DROP COLUMN unit_id');
            }
            if ($this->columnExists('stock_movements', 'qty_delta_base')) {
                $this->addSql('ALTER TABLE stock_movements DROP COLUMN qty_delta_base');
            }
        }

        if ($this->tableExists('producto')) {
            if ($this->indexExists('producto', 'idx_producto_base_unit')) {
                $this->addSql('ALTER TABLE producto DROP INDEX idx_producto_base_unit');
            }
            if ($this->indexExists('producto', 'idx_producto_track_inventory')) {
                $this->addSql('ALTER TABLE producto DROP INDEX idx_producto_track_inventory');
            }
            if ($this->indexExists('producto', 'idx_producto_product_type')) {
                $this->addSql('ALTER TABLE producto DROP INDEX idx_producto_product_type');
            }
            if ($this->columnExists('producto', 'base_unit_id')) {
                if ($this->foreignKeyExists('producto', 'FK_PRODUCTO_BASE_UNIT')) {
                    $this->addSql('ALTER TABLE producto DROP FOREIGN KEY FK_PRODUCTO_BASE_UNIT');
                }
                $this->addSql('ALTER TABLE producto DROP COLUMN base_unit_id');
            }
            if ($this->columnExists('producto', 'is_active')) {
                $this->addSql('ALTER TABLE producto DROP COLUMN is_active');
            }
            if ($this->columnExists('producto', 'track_inventory')) {
                $this->addSql('ALTER TABLE producto DROP COLUMN track_inventory');
            }
            if ($this->columnExists('producto', 'product_type')) {
                $this->addSql('ALTER TABLE producto DROP COLUMN product_type');
            }
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table]
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $table, 'column' => $column]
        ) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index',
            ['table' => $table, 'index' => $index]
        ) > 0;
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :fk AND constraint_type = \'FOREIGN KEY\'',
            ['table' => $table, 'fk' => $foreignKey]
        ) > 0;
    }
}
