<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Trims the sidebar to what a neighbourhood shop uses: selling, cash, stock, buying, who owes and what is left.
 *
 * What each role sees is the set of rows in `menu_rol`, and a fresh tenant inherits the whole ERP's menu.
 * Some modules disappear on their own for lack of a capability (payroll, fixed assets, accounting...), but
 * others have no such switch (restaurant, quotes, compliance), so the demo removes them explicitly. The
 * ids are those of {@see \App\Settings\Service\MenuSeedService}; a test keeps the two in step.
 */
final readonly class MinimarketMenu
{
    /** @var list<int> */
    public const array HIDDEN = [
        // Restaurante: mesas, cocina, plano del local
        830,
        831,
        832,
        833,
        834,
        835,
        // Cotizaciones
        775,
        // Cumplimiento: impuestos y PLE
        795,
        700,
        601,
        // Suscripciones
        750,
        // Planilla
        810,
        811,
        812,
        813,
        // Finanzas que un negocio de este tamaño no lleva: resumen de tesorería, cuentas de dinero,
        // conciliación bancaria, gastos de personal, activos fijos y contabilidad
        772,
        771,
        770,
        799,
        800,
        788,
    ];

    public function __construct(
        private Connection $connection,
    ) {}

    /** @return int menu entries removed from the roles */
    public function apply(): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM menu_rol WHERE menu_id IN (?)',
            [self::HIDDEN],
            [ArrayParameterType::INTEGER],
        );
    }
}
