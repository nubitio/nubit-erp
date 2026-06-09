<?php

declare(strict_types=1);

namespace App\Settings\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MenuSeedService
{
    private const array MENUS = [

        // ── Standalone top ───────────────────────────────────────────────────
        [
            'id' => 1,
            'order_index' => 0,
            'key_name' => 'Dashboard',
            'type' => 'route',
            'route' => 'dashboard',
            'icon' => 'ph ph-house-simple',
            'parent_id' => null,
        ],

        // ── VENTAS ───────────────────────────────────────────────────────────
        [
            'id' => 200,
            'order_index' => 1,
            'key_name' => 'Ventas',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-storefront',
            'parent_id' => null,
        ],
        [
            'id' => 201,
            'order_index' => 1,
            'key_name' => 'Punto de venta',
            'type' => 'route',
            'route' => 'pos',
            'icon' => 'ph ph-storefront',
            'parent_id' => 200,
        ],
        [
            'id' => 202,
            'order_index' => 2,
            'key_name' => 'Ventas',
            'type' => 'route',
            'route' => 'sale',
            'icon' => 'ph ph-receipt',
            'parent_id' => 200,
        ],
        [
            'id' => 203,
            'order_index' => 3,
            'key_name' => 'Consulta',
            'type' => 'route',
            'route' => 'sale-consult',
            'icon' => 'ph ph-magnifying-glass',
            'parent_id' => 200,
        ],
        [
            'id' => 204,
            'order_index' => 4,
            'key_name' => 'Clientes',
            'type' => 'route',
            'route' => 'customer',
            'icon' => 'ph ph-users',
            'parent_id' => 200,
        ],
        [
            'id' => 205,
            'order_index' => 5,
            'key_name' => 'Tarifas',
            'type' => 'route',
            'route' => 'price-list',
            'icon' => 'ph ph-tag',
            'parent_id' => 200,
        ],
        [
            'id' => 206,
            'order_index' => 6,
            'key_name' => 'Caja',
            'type' => 'route',
            'route' => 'cashbox',
            'icon' => 'ph ph-cash-register',
            'parent_id' => 200,
        ],

        // ── COMPRAS ──────────────────────────────────────────────────────────
        [
            'id' => 300,
            'order_index' => 2,
            'key_name' => 'Compras',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-shopping-cart-simple',
            'parent_id' => null,
        ],
        [
            'id' => 301,
            'order_index' => 1,
            'key_name' => 'Órdenes',
            'type' => 'route',
            'route' => 'purchases',
            'icon' => 'ph ph-shopping-cart',
            'parent_id' => 300,
        ],
        [
            'id' => 302,
            'order_index' => 2,
            'key_name' => 'Recepciones',
            'type' => 'route',
            'route' => 'receipt',
            'icon' => 'ph ph-package',
            'parent_id' => 300,
        ],
        [
            'id' => 303,
            'order_index' => 3,
            'key_name' => 'Proveedores',
            'type' => 'route',
            'route' => 'supplier',
            'icon' => 'ph ph-truck',
            'parent_id' => 300,
        ],

        // ── ALMACÉN ──────────────────────────────────────────────────────────
        [
            'id' => 100,
            'order_index' => 3,
            'key_name' => 'Almacén',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-warehouse',
            'parent_id' => null,
        ],
        [
            'id' => 101,
            'order_index' => 1,
            'key_name' => 'Productos',
            'type' => 'route',
            'route' => 'product',
            'icon' => 'ph ph-cube',
            'parent_id' => 100,
        ],
        [
            'id' => 102,
            'order_index' => 2,
            'key_name' => 'Existencias',
            'type' => 'route',
            'route' => 'stock',
            'icon' => 'ph ph-stack',
            'parent_id' => 100,
        ],
        [
            'id' => 103,
            'order_index' => 3,
            'key_name' => 'Kardex',
            'type' => 'route',
            'route' => 'kardex',
            'icon' => 'ph ph-list-bullets',
            'parent_id' => 100,
        ],
        [
            'id' => 104,
            'order_index' => 4,
            'key_name' => 'Transferencias',
            'type' => 'route',
            'route' => 'transfer',
            'icon' => 'ph ph-arrows-left-right',
            'parent_id' => 100,
        ],
        [
            'id' => 105,
            'order_index' => 5,
            'key_name' => 'Salidas',
            'type' => 'route',
            'route' => 'outbound',
            'icon' => 'ph ph-arrow-square-out',
            'parent_id' => 100,
        ],
        [
            'id' => 106,
            'order_index' => 6,
            'key_name' => 'Ajustes de stock',
            'type' => 'route',
            'route' => 'stock-adjustment',
            'icon' => 'ph ph-sliders-horizontal',
            'parent_id' => 100,
        ],
        [
            'id' => 107,
            'order_index' => 7,
            'key_name' => 'Reabastecimiento',
            'type' => 'route',
            'route' => 'reorder',
            'icon' => 'ph ph-arrow-counter-clockwise',
            'parent_id' => 100,
        ],

        // ── FINANZAS ─────────────────────────────────────────────────────────
        [
            'id' => 400,
            'order_index' => 4,
            'key_name' => 'Finanzas',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-bank',
            'parent_id' => null,
        ],
        [
            'id' => 401,
            'order_index' => 1,
            'key_name' => 'Facturación',
            'type' => 'route',
            'route' => 'invoice',
            'icon' => 'ph ph-file-text',
            'parent_id' => 400,
        ],
        [
            'id' => 402,
            'order_index' => 2,
            'key_name' => 'Notas de crédito',
            'type' => 'route',
            'route' => 'credit-note',
            'icon' => 'ph ph-file-minus',
            'parent_id' => 400,
        ],
        [
            'id' => 403,
            'order_index' => 3,
            'key_name' => 'Tipo de cambio',
            'type' => 'route',
            'route' => 'exchange-rate',
            'icon' => 'ph ph-currency-dollar',
            'parent_id' => 400,
        ],
        [
            'id' => 404,
            'order_index' => 4,
            'key_name' => 'Mov. de caja',
            'type' => 'route',
            'route' => 'cash-movement-category',
            'icon' => 'ph ph-coins',
            'parent_id' => 400,
        ],

        // ── CUMPLIMIENTO ─────────────────────────────────────────────────────
        [
            'id' => 600,
            'order_index' => 5,
            'key_name' => 'Cumplimiento',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-seal-check',
            'parent_id' => null,
        ],
        [
            'id' => 601,
            'order_index' => 1,
            'key_name' => 'Catálogos SUNAT',
            'type' => 'route',
            'route' => 'sunat-catalog',
            'icon' => 'ph ph-book-open',
            'parent_id' => 600,
        ],
        [
            'id' => 602,
            'order_index' => 2,
            'key_name' => 'Series',
            'type' => 'route',
            'route' => 'voucher-series',
            'icon' => 'ph ph-list-numbers',
            'parent_id' => 600,
        ],
        [
            'id' => 603,
            'order_index' => 3,
            'key_name' => 'Tipos comprobante',
            'type' => 'route',
            'route' => 'voucher-type',
            'icon' => 'ph ph-identification-card',
            'parent_id' => 600,
        ],
        [
            'id' => 604,
            'order_index' => 4,
            'key_name' => 'Métodos de pago',
            'type' => 'route',
            'route' => 'payment-method',
            'icon' => 'ph ph-credit-card',
            'parent_id' => 600,
        ],
        [
            'id' => 605,
            'order_index' => 5,
            'key_name' => 'Tipos de operación',
            'type' => 'route',
            'route' => 'operation',
            'icon' => 'ph ph-arrows-clockwise',
            'parent_id' => 600,
        ],

        // ── Standalone bottom ─────────────────────────────────────────────────
        [
            'id' => 2,
            'order_index' => 98,
            'key_name' => 'Reportes',
            'type' => 'route',
            'route' => 'reports',
            'icon' => 'ph ph-chart-bar',
            'parent_id' => null,
        ],

        // ── ADMINISTRACIÓN ───────────────────────────────────────────────────
        [
            'id' => 900,
            'order_index' => 99,
            'key_name' => 'Administración',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-shield-chevron',
            'parent_id' => null,
        ],
        [
            'id' => 901,
            'order_index' => 1,
            'key_name' => 'Usuarios',
            'type' => 'route',
            'route' => 'user',
            'icon' => 'ph ph-user-gear',
            'parent_id' => 900,
        ],
        [
            'id' => 902,
            'order_index' => 2,
            'key_name' => 'Roles',
            'type' => 'route',
            'route' => 'role',
            'icon' => 'ph ph-shield-check',
            'parent_id' => 900,
        ],
        [
            'id' => 903,
            'order_index' => 3,
            'key_name' => 'Empresa',
            'type' => 'route',
            'route' => 'company',
            'icon' => 'ph ph-buildings',
            'parent_id' => 900,
        ],
        [
            'id' => 904,
            'order_index' => 4,
            'key_name' => 'Sucursales',
            'type' => 'route',
            'route' => 'branch',
            'icon' => 'ph ph-map-pin',
            'parent_id' => 900,
        ],
        [
            'id' => 905,
            'order_index' => 5,
            'key_name' => 'Almacenes',
            'type' => 'route',
            'route' => 'warehouse',
            'icon' => 'ph ph-warehouse',
            'parent_id' => 900,
        ],
        [
            'id' => 906,
            'order_index' => 6,
            'key_name' => 'Categorías',
            'type' => 'route',
            'route' => 'category',
            'icon' => 'ph ph-folder',
            'parent_id' => 900,
        ],
        [
            'id' => 907,
            'order_index' => 7,
            'key_name' => 'Unidades de medida',
            'type' => 'route',
            'route' => 'unit-of-measure',
            'icon' => 'ph ph-ruler',
            'parent_id' => 900,
        ],
        [
            'id' => 908,
            'order_index' => 8,
            'key_name' => 'Cajas',
            'type' => 'route',
            'route' => 'cash-register',
            'icon' => 'ph ph-cash-register',
            'parent_id' => 900,
        ],
        [
            'id' => 909,
            'order_index' => 9,
            'key_name' => 'Configuración',
            'type' => 'route',
            'route' => 'config',
            'icon' => 'ph ph-gear-six',
            'parent_id' => 900,
        ],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Seed menus idempotently.
     *
     * @throws Exception
     */
    public function seed(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->connection->executeStatement('DELETE FROM menu');
        $this->connection->executeStatement('DELETE FROM menu_rol');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        foreach (self::MENUS as $menu) {
            $this->connection->executeStatement(
                'INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id)
                VALUES (:id, :order_index, :key_name, :type, :route, :icon, :parent_id)
                ON DUPLICATE KEY UPDATE
                    order_index = VALUES(order_index),
                    key_name = VALUES(key_name),
                    type = VALUES(type),
                    route = VALUES(route),
                    icon = VALUES(icon),
                    parent_id = VALUES(parent_id)',
                $menu
            );
        }

        // Grant all roles access to all menus (role-level filtering handled elsewhere).
        $this->connection->executeStatement(
            'INSERT INTO menu_rol (menu_id, role_id)
             SELECT m.id, r.rol_id FROM menu m CROSS JOIN rol r'
        );
    }
}
