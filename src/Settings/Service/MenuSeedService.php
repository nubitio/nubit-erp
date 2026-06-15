<?php

declare(strict_types=1);

namespace App\Settings\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ArrayParameterType;

class MenuSeedService
{
    private const array MENUS = [
        // Superficie MVP: solo las entradas necesarias para vender, facturar,
        // operar caja y configurar el tenant. Las rutas avanzadas siguen
        // registradas, pero no se exponen en el menú inicial.
        [
            'id' => 1,
            'order_index' => 0,
            'key_name' => 'Dashboard',
            'type' => 'route',
            'route' => 'dashboard',
            'icon' => 'ph ph-house-simple',
            'parent_id' => null,
        ],

        [
            'id' => 201,
            'order_index' => 1,
            'key_name' => 'Punto de venta',
            'type' => 'route',
            'route' => 'pos',
            'icon' => 'ph ph-storefront',
            'parent_id' => null,
        ],
        [
            'id' => 401,
            'order_index' => 2,
            'key_name' => 'Comprobantes',
            'type' => 'route',
            'route' => 'invoice',
            'icon' => 'ph ph-file-text',
            'parent_id' => null,
        ],
        [
            'id' => 101,
            'order_index' => 3,
            'key_name' => 'Productos',
            'type' => 'route',
            'route' => 'product',
            'icon' => 'ph ph-cube',
            'parent_id' => null,
        ],
        [
            'id' => 204,
            'order_index' => 4,
            'key_name' => 'Clientes',
            'type' => 'route',
            'route' => 'customer',
            'icon' => 'ph ph-users',
            'parent_id' => null,
        ],
        [
            'id' => 206,
            'order_index' => 5,
            'key_name' => 'Caja',
            'type' => 'route',
            'route' => 'cashbox',
            'icon' => 'ph ph-cash-register',
            'parent_id' => null,
        ],
        [
            'id' => 909,
            'order_index' => 6,
            'key_name' => 'Configuración',
            'type' => 'route',
            'route' => 'config',
            'icon' => 'ph ph-gear-six',
            'parent_id' => null,
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

    /**
     * Apply the MVP menu to an existing tenant without touching tenant settings.
     * Missing MVP role assignments are backfilled to match initial seed behavior.
     *
     * @throws Exception
     */
    public function syncMvpPreservingRoleAccess(): void
    {
        $menuIds = array_column(self::MENUS, 'id');

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

        $this->connection->executeStatement(
            'DELETE FROM menu_rol WHERE menu_id NOT IN (?)',
            [$menuIds],
            [ArrayParameterType::INTEGER],
        );
        $this->connection->executeStatement(
            'DELETE FROM menu WHERE id NOT IN (?)',
            [$menuIds],
            [ArrayParameterType::INTEGER],
        );
        $this->connection->executeStatement(
            'INSERT IGNORE INTO menu_rol (menu_id, role_id)
             SELECT m.id, r.rol_id FROM menu m CROSS JOIN rol r WHERE m.id IN (?)',
            [$menuIds],
            [ArrayParameterType::INTEGER],
        );
    }
}
