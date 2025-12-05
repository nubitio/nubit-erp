<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251205023012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed menu table with initial structure (idempotent).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id) VALUES
    (1, 1, 'Dashboard', 'route', 'dashboard', 'ph ph-house', NULL),
    (100, 1, 'Almacén', 'heading', NULL, 'ph ph-warehouse', NULL),
    (200, 2, 'Ventas', 'heading', NULL, 'ph ph-shopping-cart', NULL),
    (300, 3, 'Caja', 'heading', NULL, 'ph ph-coins', NULL),
    (400, 5, 'Contabilidad', 'heading', NULL, 'ph ph-calculator', NULL),
    (900, 6, 'Seguridad', 'heading', NULL, 'ph ph-shield-chevron', NULL),
    (1000, 7, 'Sistema', 'heading', NULL, 'ph ph-gear', NULL),
    (2000, 8, 'Developer', 'heading', NULL, 'ph ph-atom', NULL),
    (101, 1, 'Proveedores', 'route', 'supplier', '', 100),
    (102, 2, 'Productos', 'route', 'product', '', 100),
    (103, 3, 'Tarifas', 'route', 'price-list', '', 100),
    (104, 4, 'Ingresos', 'route', 'inbound', '', 100),
    (105, 5, 'Salidas', 'route', 'outbound', '', 100),
    (106, 6, 'Transferencias', 'route', 'transfer', '', 100),
    (107, 7, 'Movimientos', 'route', 'kardex', '', 100),
    (108, 8, 'Unidades de medida', 'route', 'unit-of-measure', '', 100),
    (202, 1, 'Registro ventas', 'route', 'sale', '', 200),
    (203, 2, 'Consulta ventas', 'route', 'sale-consult', '', 200),
    (204, 3, 'Clientes', 'route', 'customer', '', 200),
    (301, 1, 'Registro caja', 'route', 'cashbox', '', 300),
    (302, 2, 'Consulta caja', 'route', 'cashbox-consult', '', 300),
    (303, 3, 'Cajas', 'route', 'cash-register', '', 300),
    (401, 1, 'Facturación', 'route', 'invoice', '', 400),
    (402, 2, 'Nota de Crédito', 'route', 'credit-note', '', 400),
    (901, 1, 'Usuarios', 'route', 'user', '', 900),
    (902, 2, 'Roles', 'route', 'role', '', 900),
    (1001, 1, 'Empresas', 'route', 'company', '', 1000),
    (1002, 2, 'Locales', 'route', 'branch', '', 1000),
    (1003, 3, 'Almacenes', 'route', 'warehouse', '', 1000),
    (1004, 4, 'Categorias', 'route', 'category', '', 1000),
    (1005, 5, 'Transacciones', 'route', 'operation', '', 1000),
    (1006, 6, 'Tipo comprobante', 'route', 'voucher-type', '', 1000),
    (1007, 7, 'Local comprobante', 'route', 'voucher-series', '', 1000),
    (2001, 1, 'Configuración', 'route', 'config', '', 2000)
ON DUPLICATE KEY UPDATE
    order_index = VALUES(order_index),
    key_name = VALUES(key_name),
    type = VALUES(type),
    route = VALUES(route),
    icon = VALUES(icon),
    parent_id = VALUES(parent_id);
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM menu');
    }
}
