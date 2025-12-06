<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MenuSeedService
{
    private const array MENUS = [
        [
            'id' => 1,
            'order_index' => 1,
            'key_name' => 'Dashboard',
            'type' => 'route',
            'route' => 'dashboard',
            'icon' => 'ph ph-house',
            'parent_id' => null,
        ],
        [
            'id' => 100,
            'order_index' => 1,
            'key_name' => 'Almacén',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-warehouse',
            'parent_id' => null,
        ],
        [
            'id' => 200,
            'order_index' => 2,
            'key_name' => 'Ventas',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-shopping-cart',
            'parent_id' => null,
        ],
        [
            'id' => 300,
            'order_index' => 3,
            'key_name' => 'Caja',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-coins',
            'parent_id' => null,
        ],
        [
            'id' => 400,
            'order_index' => 5,
            'key_name' => 'Contabilidad',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-calculator',
            'parent_id' => null,
        ],
        [
            'id' => 900,
            'order_index' => 6,
            'key_name' => 'Seguridad',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-shield-chevron',
            'parent_id' => null,
        ],
        [
            'id' => 1000,
            'order_index' => 7,
            'key_name' => 'Sistema',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-gear',
            'parent_id' => null,
        ],
        [
            'id' => 2000,
            'order_index' => 8,
            'key_name' => 'Developer',
            'type' => 'heading',
            'route' => null,
            'icon' => 'ph ph-atom',
            'parent_id' => null,
        ],
        [
            'id' => 101,
            'order_index' => 1,
            'key_name' => 'Proveedores',
            'type' => 'route',
            'route' => 'supplier',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 102,
            'order_index' => 2,
            'key_name' => 'Productos',
            'type' => 'route',
            'route' => 'product',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 103,
            'order_index' => 3,
            'key_name' => 'Tarifas',
            'type' => 'route',
            'route' => 'price-list',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 104,
            'order_index' => 4,
            'key_name' => 'Ingresos',
            'type' => 'route',
            'route' => 'inbound',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 105,
            'order_index' => 5,
            'key_name' => 'Salidas',
            'type' => 'route',
            'route' => 'outbound',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 106,
            'order_index' => 6,
            'key_name' => 'Transferencias',
            'type' => 'route',
            'route' => 'transfer',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 107,
            'order_index' => 7,
            'key_name' => 'Movimientos',
            'type' => 'route',
            'route' => 'kardex',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 108,
            'order_index' => 8,
            'key_name' => 'Unidades de medida',
            'type' => 'route',
            'route' => 'unit-of-measure',
            'icon' => '',
            'parent_id' => 100,
        ],
        [
            'id' => 202,
            'order_index' => 1,
            'key_name' => 'Registro ventas',
            'type' => 'route',
            'route' => 'sale',
            'icon' => '',
            'parent_id' => 200,
        ],
        [
            'id' => 203,
            'order_index' => 2,
            'key_name' => 'Consulta ventas',
            'type' => 'route',
            'route' => 'sale-consult',
            'icon' => '',
            'parent_id' => 200,
        ],
        [
            'id' => 204,
            'order_index' => 3,
            'key_name' => 'Clientes',
            'type' => 'route',
            'route' => 'customer',
            'icon' => '',
            'parent_id' => 200,
        ],
        [
            'id' => 301,
            'order_index' => 1,
            'key_name' => 'Registro caja',
            'type' => 'route',
            'route' => 'cashbox',
            'icon' => '',
            'parent_id' => 300,
        ],
        [
            'id' => 302,
            'order_index' => 2,
            'key_name' => 'Consulta caja',
            'type' => 'route',
            'route' => 'cashbox-consult',
            'icon' => '',
            'parent_id' => 300,
        ],
        [
            'id' => 303,
            'order_index' => 3,
            'key_name' => 'Cajas',
            'type' => 'route',
            'route' => 'cash-register',
            'icon' => '',
            'parent_id' => 300,
        ],
        [
            'id' => 401,
            'order_index' => 1,
            'key_name' => 'Facturación',
            'type' => 'route',
            'route' => 'invoice',
            'icon' => '',
            'parent_id' => 400,
        ],
        [
            'id' => 402,
            'order_index' => 2,
            'key_name' => 'Nota de Crédito',
            'type' => 'route',
            'route' => 'credit-note',
            'icon' => '',
            'parent_id' => 400,
        ],
        [
            'id' => 901,
            'order_index' => 1,
            'key_name' => 'Usuarios',
            'type' => 'route',
            'route' => 'user',
            'icon' => '',
            'parent_id' => 900,
        ],
        [
            'id' => 902,
            'order_index' => 2,
            'key_name' => 'Roles',
            'type' => 'route',
            'route' => 'role',
            'icon' => '',
            'parent_id' => 900,
        ],
        [
            'id' => 1001,
            'order_index' => 1,
            'key_name' => 'Empresas',
            'type' => 'route',
            'route' => 'company',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 1002,
            'order_index' => 2,
            'key_name' => 'Locales',
            'type' => 'route',
            'route' => 'branch',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 1003,
            'order_index' => 3,
            'key_name' => 'Almacenes',
            'type' => 'route',
            'route' => 'warehouse',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 1004,
            'order_index' => 4,
            'key_name' => 'Categorias',
            'type' => 'route',
            'route' => 'category',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 1005,
            'order_index' => 5,
            'key_name' => 'Transacciones',
            'type' => 'route',
            'route' => 'operation',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 1006,
            'order_index' => 6,
            'key_name' => 'Tipo comprobante',
            'type' => 'route',
            'route' => 'voucher-type',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 1007,
            'order_index' => 7,
            'key_name' => 'Local comprobante',
            'type' => 'route',
            'route' => 'voucher-series',
            'icon' => '',
            'parent_id' => 1000,
        ],
        [
            'id' => 2001,
            'order_index' => 1,
            'key_name' => 'Configuración',
            'type' => 'route',
            'route' => 'config',
            'icon' => '',
            'parent_id' => 2000,
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
    }
}
