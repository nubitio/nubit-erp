<?php

declare(strict_types=1);

namespace App\Settings\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MenuSeedService
{
    /**
     * Menú operativo agrupado: uso diario sin acordeón, y tres grupos
     * colapsables (Compras, Finanzas, Cumplimiento) que unifican pantallas
     * hermanas hoy dispersas como ítems planos. Un ítem "grupo" tiene
     * route=null y solo actúa como toggle (ver MenuProvider::buildMenuForRole);
     * las rutas de sus hijos no cambian, así que RBAC y bookmarks existentes
     * siguen funcionando. La visibilidad final se refina con
     * TenantRuntimeConfig en menuService.ts.
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
        // ── Uso diario (siempre un clic, sin acordeón) ──────────────────────
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

        // ── Grupo: Compras (recepción directa + con orden de compra) ───────
        [
            'id' => 510,
            'order_index' => 3,
            'key_name' => 'Compras',
            'type' => 'group',
            'route' => null,
            'icon' => 'ph ph-shopping-bag',
            'parent_id' => null,
        ],
        [
            'id' => 500,
            'order_index' => 0,
            'key_name' => 'Recepción de mercadería',
            'type' => 'route',
            'route' => 'purchases',
            'icon' => 'ph ph-shopping-bag',
            'parent_id' => 510,
        ],
        [
            'id' => 780,
            'order_index' => 1,
            'key_name' => 'Órdenes de compra',
            'type' => 'route',
            'route' => 'purchase-orders',
            'icon' => 'ph ph-clipboard-text',
            'parent_id' => 510,
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
        [
            'id' => 750,
            'order_index' => 8,
            'key_name' => 'Suscripciones',
            'type' => 'route',
            'route' => 'subscriptions',
            'icon' => 'ph ph-arrows-clockwise',
            'parent_id' => null,
        ],

        // ── Grupo: Finanzas (cuentas por pagar/cobrar + conciliación) ──────
        [
            'id' => 790,
            'order_index' => 9,
            'key_name' => 'Finanzas',
            'type' => 'group',
            'route' => null,
            'icon' => 'ph ph-wallet',
            'parent_id' => null,
        ],
        [
            'id' => 760,
            'order_index' => 0,
            'key_name' => 'Cuentas por pagar',
            'type' => 'route',
            'route' => 'accounts-payable',
            'icon' => 'ph ph-invoice',
            'parent_id' => 790,
        ],
        [
            'id' => 765,
            'order_index' => 1,
            'key_name' => 'Cuentas por cobrar',
            'type' => 'route',
            'route' => 'accounts-receivable',
            'icon' => 'ph ph-hand-coins',
            'parent_id' => 790,
        ],
        [
            'id' => 771,
            'order_index' => 2,
            'key_name' => 'Cuentas de dinero',
            'type' => 'route',
            'route' => 'treasury',
            'icon' => 'ph ph-bank',
            'parent_id' => 790,
        ],
        [
            'id' => 770,
            'order_index' => 3,
            'key_name' => 'Conciliación bancaria',
            'type' => 'route',
            'route' => 'bank-reconciliation',
            'icon' => 'ph ph-arrows-left-right',
            'parent_id' => 790,
        ],
        [
            'id' => 785,
            'order_index' => 4,
            'key_name' => 'Reportes financieros',
            'type' => 'route',
            'route' => 'financial-reports',
            'icon' => 'ph ph-chart-pie-slice',
            'parent_id' => 790,
        ],
        [
            'id' => 799,
            'order_index' => 5,
            'key_name' => 'Gastos de personal',
            'type' => 'route',
            'route' => 'expense-reports',
            'icon' => 'ph ph-airplane-tilt',
            'parent_id' => 790,
        ],
        [
            'id' => 800,
            'order_index' => 6,
            'key_name' => 'Activos fijos',
            'type' => 'route',
            'route' => 'fixed-assets',
            'icon' => 'ph ph-buildings',
            'parent_id' => 790,
        ],
        [
            'id' => 788,
            'order_index' => 7,
            'key_name' => 'Contabilidad',
            'type' => 'route',
            'route' => 'accounting',
            'icon' => 'ph ph-books',
            'parent_id' => 790,
        ],

        [
            'id' => 775,
            'order_index' => 10,
            'key_name' => 'Cotizaciones',
            'type' => 'route',
            'route' => 'sale-quotes',
            'icon' => 'ph ph-file-text',
            'parent_id' => null,
        ],

        // ── Grupo: Planilla (empleados, corridas de planilla, PLAME) ────────
        [
            'id' => 810,
            'order_index' => 11,
            'key_name' => 'Planilla',
            'type' => 'group',
            'route' => null,
            'icon' => 'ph ph-users-three',
            'parent_id' => null,
        ],
        [
            'id' => 811,
            'order_index' => 0,
            'key_name' => 'Empleados',
            'type' => 'route',
            'route' => 'employees',
            'icon' => 'ph ph-identification-card',
            'parent_id' => 810,
        ],
        [
            'id' => 812,
            'order_index' => 1,
            'key_name' => 'Corridas de planilla',
            'type' => 'route',
            'route' => 'payroll-runs',
            'icon' => 'ph ph-calculator',
            'parent_id' => 810,
        ],
        [
            'id' => 813,
            'order_index' => 2,
            'key_name' => 'Tasas de AFP',
            'type' => 'route',
            'route' => 'afp-rates',
            'icon' => 'ph ph-percent',
            'parent_id' => 810,
        ],

        // ── Grupo: Cumplimiento (obligaciones periódicas SUNAT) ─────────────
        [
            'id' => 795,
            'order_index' => 12,
            'key_name' => 'Cumplimiento',
            'type' => 'group',
            'route' => null,
            'icon' => 'ph ph-shield-check',
            'parent_id' => null,
        ],
        [
            'id' => 700,
            'order_index' => 0,
            'key_name' => 'Impuestos',
            'type' => 'route',
            'route' => 'taxes',
            'icon' => 'ph ph-receipt',
            'parent_id' => 795,
        ],
        [
            'id' => 601,
            'order_index' => 1,
            'key_name' => 'Exportación PLE',
            'type' => 'route',
            'route' => 'ple',
            'icon' => 'ph ph-export',
            'parent_id' => 795,
        ],

        [
            'id' => 909,
            'order_index' => 13,
            'key_name' => 'Configuración',
            'type' => 'group',
            'route' => null,
            'icon' => 'ph ph-gear-six',
            'parent_id' => null,
        ],
        [
            'id' => 908,
            'order_index' => 0,
            'key_name' => 'Preferencias generales',
            'type' => 'route',
            'route' => 'config',
            'icon' => 'ph ph-sliders-horizontal',
            'parent_id' => 909,
        ],
        [
            'id' => 820,
            'order_index' => 1,
            'key_name' => 'Migración inicial',
            'type' => 'route',
            'route' => 'setup/import',
            'icon' => 'ph ph-database',
            'parent_id' => 909,
        ],

        // ── Grupo: Restaurante (mesas y cocina, NB-18) ──────────────────────
        [
            'id' => 830,
            'order_index' => 14,
            'key_name' => 'Restaurante',
            'type' => 'group',
            'route' => null,
            'icon' => 'ph ph-fork-knife',
            'parent_id' => null,
        ],
        [
            'id' => 831,
            'order_index' => 0,
            'key_name' => 'Mesas',
            'type' => 'route',
            'route' => 'restaurant/mesas',
            'icon' => 'ph ph-squares-four',
            'parent_id' => 830,
        ],
        [
            'id' => 832,
            'order_index' => 1,
            'key_name' => 'Cocina',
            'type' => 'route',
            'route' => 'restaurant/kds',
            'icon' => 'ph ph-cooking-pot',
            'parent_id' => 830,
        ],
        [
            'id' => 835,
            'order_index' => 2,
            'key_name' => 'Ambientes y plantas',
            'type' => 'route',
            'route' => 'restaurant/admin/ambientes',
            'icon' => 'ph ph-buildings',
            'parent_id' => 830,
        ],
        [
            'id' => 833,
            'order_index' => 3,
            'key_name' => 'Configurar mesas',
            'type' => 'route',
            'route' => 'restaurant/admin/mesas',
            'icon' => 'ph ph-list-bullets',
            'parent_id' => 830,
        ],
        [
            'id' => 834,
            'order_index' => 4,
            'key_name' => 'Plano del local',
            'type' => 'route',
            'route' => 'restaurant/admin/plano',
            'icon' => 'ph ph-map-trifold',
            'parent_id' => 830,
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
        // Detach legacy children before deleting: the self-referencing FK on
        // parent_id rejects removing a parent while any row still points at it.
        $this->connection->executeStatement(
            'UPDATE menu SET parent_id = NULL WHERE id NOT IN (?)',
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
