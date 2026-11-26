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
                    'sales.view_own' => 'Ver los comprobantes de sus propias ventas',
                    'sales.create' => 'Emitir comprobantes',
                    'sales.void' => 'Anular comprobantes',
                    'sales.export' => 'Exportar ventas',
                    'sales.collect' => 'Registrar cobros',
                ],
            ],
            'pos' => [
                'label' => 'Punto de venta',
                'permissions' => [
                    'pos.access' => 'Acceder al POS',
                    'pos.discount' => 'Aplicar descuentos',
                    'pos.price_override' => 'Modificar precio de línea en el POS',
                    'pos.offline_sale' => 'Sincronizar ventas offline',
                ],
            ],
            'cash' => [
                'label' => 'Caja',
                'permissions' => [
                    'cash.view' => 'Ver caja',
                    'cash.open' => 'Abrir caja',
                    'cash.close' => 'Cerrar caja',
                    'cash.close_any' => 'Cerrar cajas de otros usuarios',
                    'cash.movement' => 'Registrar movimientos',
                    'cash.movement_any' => 'Registrar movimientos en cajas de otros usuarios',
                    'cash.manage' => 'Administrar cajas',
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
                    'catalog.edit' => 'Editar productos y precios del catálogo',
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
                    'purchases.cross_branch' => 'Gestionar compras de otras sucursales operativas',
                    'purchases.edit' => 'Editar compras',
                    'purchases.void' => 'Anular compras',
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
            'activity' => [
                'label' => 'Registro de actividad',
                'permissions' => [
                    'activity.view' => 'Ver el registro de actividad',
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
                    'subscriptions.generate' => 'Generar cargos de suscripción',
                    'subscriptions.cancel' => 'Cancelar suscripciones',
                ],
            ],
            'receivables' => [
                'label' => 'Cuentas por cobrar',
                'permissions' => [
                    'receivables.remind' => 'Enviar recordatorios de cobro',
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
            'expense_reports' => [
                'label' => 'Gastos de personal',
                'permissions' => [
                    'expense_reports.view' => 'Ver gastos de personal',
                    'expense_reports.create' => 'Registrar gastos de personal',
                    'expense_reports.approve' => 'Aprobar o rechazar gastos de personal',
                    'expense_reports.reimburse' => 'Marcar gastos como reembolsados',
                ],
            ],
            'fixed_assets' => [
                'label' => 'Activos fijos',
                'permissions' => [
                    'fixed_assets.view' => 'Ver activos fijos',
                    'fixed_assets.create' => 'Registrar activos fijos',
                    'fixed_assets.dispose' => 'Dar de baja activos fijos',
                ],
            ],
            'bank_reconciliation' => [
                'label' => 'Conciliación bancaria',
                'permissions' => [
                    'bank_reconciliation.view' => 'Ver conciliación bancaria',
                    'bank_reconciliation.import' => 'Importar extractos bancarios',
                    'bank_reconciliation.match' => 'Conciliar movimientos',
                    'bank_reconciliation.void' => 'Deshacer conciliaciones',
                ],
            ],
            'treasury' => [
                'label' => 'Cuentas de dinero',
                'permissions' => [
                    'treasury.view' => 'Ver cuentas y movimientos de dinero',
                    'treasury.manage' => 'Administrar cuentas y métodos de pago',
                    'treasury.transfer' => 'Registrar transferencias entre cuentas',
                ],
            ],
            'sale_quotes' => [
                'label' => 'Cotizaciones',
                'permissions' => [
                    'sale_quotes.view' => 'Ver cotizaciones',
                    'sale_quotes.create' => 'Crear cotizaciones',
                    'sale_quotes.edit' => 'Editar cotizaciones',
                    'sale_quotes.send' => 'Enviar cotizaciones',
                    'sale_quotes.convert' => 'Convertir a venta',
                    'sale_quotes.void' => 'Eliminar cotizaciones',
                ],
            ],
            'purchase_orders' => [
                'label' => 'Órdenes de compra',
                'permissions' => [
                    'purchase_orders.view' => 'Ver órdenes de compra',
                    'purchase_orders.create' => 'Crear órdenes de compra',
                    'purchase_orders.edit' => 'Editar órdenes de compra',
                    'purchase_orders.send' => 'Enviar órdenes de compra al proveedor',
                    'purchase_orders.receive' => 'Recibir mercadería contra una orden de compra',
                    'purchase_orders.cancel' => 'Cancelar órdenes de compra',
                ],
            ],
            'payroll' => [
                'label' => 'Planilla',
                'permissions' => [
                    'payroll.view' => 'Ver planilla',
                    'payroll.manage_employees' => 'Registrar y editar empleados',
                    'payroll.manage_rates' => 'Editar tasas de AFP',
                    'payroll.run' => 'Calcular corridas de planilla',
                    'payroll.approve' => 'Aprobar y marcar como pagadas las corridas de planilla',
                    'payroll.export_plame' => 'Exportar archivos PLAME',
                ],
            ],
            'accounting' => [
                'label' => 'Contabilidad',
                'permissions' => [
                    'accounting.view' => 'Ver libro mayor y asientos',
                ],
            ],
            'restaurant' => [
                'label' => 'Restaurante',
                'permissions' => [
                    'restaurant.view' => 'Ver mesas, comandas y cocina',
                    'restaurant.operate' => 'Abrir mesas, tomar comandas y cobrar',
                    'restaurant.manage' => 'Crear y editar mesas y ambientes',
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

    /** Human label of a permission key; falls back to the key itself. */
    public static function label(string $permission): string
    {
        foreach (self::modules() as $module) {
            if (isset($module['permissions'][$permission])) {
                return $module['permissions'][$permission];
            }
        }

        return $permission;
    }

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::keys(), strict: true);
    }
}
