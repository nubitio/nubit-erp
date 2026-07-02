<?php

declare(strict_types=1);

namespace App\Settings\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MenuSeedService
{
    /**
     * Menú operativo por capas: operación diaria, crecimiento y enterprise.
     * Las rutas avanzadas siguen registradas en el frontend; la visibilidad
     * final se refina con TenantRuntimeConfig en menuService.ts.
     *
     * @var list<array{
     *     id: int,
     *     order_index: int,
     *     key_name: string,
     *     type: string,
     *     route: string|null,
     *     icon: string|null,
     *     parent_id: int|null
     * }>
     */
    private const array MENUS = [
        // ── Nivel 1: operación diaria ───────────────────────────────────────
        [
            'id' => 1,
            'order_index' => 0,
            'key_name' => 'Inicio',
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
            'id' => 300,
            'order_index' => 2,
            'key_name' => 'Facturación',
            'type' => 'route',
            'route' => 'billing',
            'icon' => 'ph ph-chart-line-up',
            'parent_id' => null,
        ],

        // ── Nivel 2: crecimiento ────────────────────────────────────────────
        [
            'id' => 500,
            'order_index' => 3,
            'key_name' => 'Compras',
            'type' => 'route',
            'route' => 'purchases',
            'icon' => 'ph ph-shopping-bag',
            'parent_id' => null,
        ],
        [
            'id' => 101,
            'order_index' => 4,
            'key_name' => 'Catálogo',
            'type' => 'route',
            'route' => 'catalog',
            'icon' => 'ph ph-cube',
            'parent_id' => null,
        ],
        [
            'id' => 550,
            'order_index' => 5,
            'key_name' => 'Inventario',
            'type' => 'route',
            'route' => 'inventory',
            'icon' => 'ph ph-warehouse',
            'parent_id' => null,
        ],
        [
            'id' => 204,
            'order_index' => 6,
            'key_name' => 'Clientes',
            'type' => 'route',
            'route' => 'customer',
            'icon' => 'ph ph-users',
            'parent_id' => null,
        ],
        [
            'id' => 206,
            'order_index' => 7,
            'key_name' => 'Caja',
            'type' => 'route',
            'route' => 'cashbox',
            'icon' => 'ph ph-cash-register',
            'parent_id' => null,
        ],

        // ── Nivel 3: enterprise (filtrado por runtime config) ─────────────
        [
            'id' => 700,
            'order_index' => 8,
            'key_name' => 'Impuestos',
            'type' => 'route',
            'route' => 'taxes',
            'icon' => 'ph ph-receipt',
            'parent_id' => null,
        ],
        [
            'id' => 601,
            'order_index' => 9,
            'key_name' => 'Exportación PLE',
            'type' => 'route',
            'route' => 'ple',
            'icon' => 'ph ph-export',
            'parent_id' => null,
        ],
        [
            'id' => 909,
            'order_index' => 10,
            'key_name' => 'Configuración',
            'type' => 'route',
            'route' => 'config',
            'icon' => 'ph ph-gear-six',
            'parent_id' => null,
        ],
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {}

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
            $this->upsertMenu($menu);
        }

        // Grant all roles access to all menus (role-level filtering handled elsewhere).
        $this->connection->executeStatement('INSERT INTO menu_rol (menu_id, role_id)
             SELECT m.id, r.rol_id FROM menu m CROSS JOIN rol r');
    }

    /**
     * Apply the layered menu to an existing tenant without touching tenant settings.
     * Missing role assignments are backfilled to match initial seed behavior.
     *
     * @throws Exception
     */
    public function syncMvpPreservingRoleAccess(): void
    {
        $menuIds = array_column(self::MENUS, 'id');

        foreach (self::MENUS as $menu) {
            $this->upsertMenu($menu);
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

    /**
     * @param array{
     *     id: int,
     *     order_index: int,
     *     key_name: string,
     *     type: string,
     *     route: string|null,
     *     icon: string|null,
     *     parent_id: int|null
     * } $menu
     *
     * @throws Exception
     */
    private function upsertMenu(array $menu): void
    {
        $this->connection->executeStatement('INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id)
            VALUES (:id, :order_index, :key_name, :type, :route, :icon, :parent_id)
            ON DUPLICATE KEY UPDATE
                order_index = VALUES(order_index),
                key_name = VALUES(key_name),
                type = VALUES(type),
                route = VALUES(route),
                icon = VALUES(icon),
                parent_id = VALUES(parent_id)', $menu);
    }
}
