<?php

declare(strict_types=1);

namespace App\Identity\Security;

/**
 * Code-defined catalog of granular permissions, grouped by module.
 *
 * Keys use the `module.action` convention and are what gets persisted in
 * role_permission and checked via is_granted('perm:module.action').
 * Adding a permission here makes it immediately available in the role
 * permission manager UI — no migration or seed needed.
 */
final class PermissionCatalog
{
    /**
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        return [
            'sales' => [
                'label' => 'Ventas',
                'permissions' => [
                    'sales.view' => 'Ver comprobantes',
                    'sales.create' => 'Emitir comprobantes',
                    'sales.void' => 'Anular comprobantes',
                    'sales.export' => 'Exportar ventas',
                ],
            ],
            'pos' => [
                'label' => 'Punto de venta',
                'permissions' => [
                    'pos.access' => 'Acceder al POS',
                    'pos.discount' => 'Aplicar descuentos',
                    'pos.price_override' => 'Modificar precios',
                ],
            ],
            'cash' => [
                'label' => 'Caja',
                'permissions' => [
                    'cash.view' => 'Ver caja',
                    'cash.open' => 'Abrir caja',
                    'cash.close' => 'Cerrar caja',
                    'cash.movement' => 'Registrar movimientos',
                ],
            ],
            'inventory' => [
                'label' => 'Inventario',
                'permissions' => [
                    'inventory.view' => 'Ver inventario',
                    'inventory.adjust' => 'Ajustar stock',
                    'inventory.transfer' => 'Transferir entre sucursales',
                    'inventory.cycle_count' => 'Conteo cíclico',
                ],
            ],
            'catalog' => [
                'label' => 'Catálogo',
                'permissions' => [
                    'catalog.view' => 'Ver productos',
                    'catalog.create' => 'Crear productos',
                    'catalog.edit' => 'Editar productos',
                    'catalog.delete' => 'Eliminar productos',
                ],
            ],
            'customers' => [
                'label' => 'Clientes',
                'permissions' => [
                    'customers.view' => 'Ver clientes',
                    'customers.create' => 'Crear clientes',
                    'customers.edit' => 'Editar clientes',
                    'customers.delete' => 'Eliminar clientes',
                ],
            ],
            'purchases' => [
                'label' => 'Compras e ingresos',
                'permissions' => [
                    'purchases.view' => 'Ver ingresos de stock',
                    'purchases.create' => 'Registrar ingresos',
                ],
            ],
            'reports' => [
                'label' => 'Reportes',
                'permissions' => [
                    'reports.view' => 'Ver reportes',
                    'reports.export' => 'Exportar reportes',
                    'reports.ple' => 'Exportar PLE',
                ],
            ],
            'settings' => [
                'label' => 'Configuración',
                'permissions' => [
                    'settings.view' => 'Ver configuración',
                    'settings.edit' => 'Editar configuración',
                ],
            ],
            'users' => [
                'label' => 'Usuarios',
                'permissions' => [
                    'users.view' => 'Ver usuarios',
                    'users.create' => 'Crear usuarios',
                    'users.edit' => 'Editar usuarios',
                    'users.delete' => 'Eliminar usuarios',
                ],
            ],
            'roles' => [
                'label' => 'Roles y permisos',
                'permissions' => [
                    'roles.view' => 'Ver roles',
                    'roles.edit' => 'Editar roles',
                    'roles.assign' => 'Asignar permisos',
                ],
            ],
            'subscriptions' => [
                'label' => 'Suscripciones y cobranzas',
                'permissions' => [
                    'subscriptions.view' => 'Ver suscripciones',
                    'subscriptions.create' => 'Crear suscripciones',
                    'subscriptions.edit' => 'Editar suscripciones',
                    'subscriptions.cancel' => 'Cancelar suscripciones',
                ],
            ],
            'accounts_payable' => [
                'label' => 'Cuentas por pagar',
                'permissions' => [
                    'accounts_payable.view' => 'Ver facturas de proveedor',
                    'accounts_payable.create' => 'Registrar facturas de proveedor',
                    'accounts_payable.pay' => 'Registrar pagos',
                    'accounts_payable.void' => 'Anular facturas de proveedor',
                ],
            ],
        ];
    }

    /**
     * Flat list of every valid permission key.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::modules() as $module) {
            foreach (array_keys($module['permissions']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::keys(), strict: true);
    }
}
