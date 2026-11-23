<?php

declare(strict_types=1);

namespace App\Tests\Setup\Demo\Minimarket;

use App\Settings\Service\MenuSeedService;
use App\Setup\Demo\Minimarket\MinimarketMenu;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

final class MinimarketMenuTest extends TestCase
{
    /** @return list<array{id: int, key_name: string, parent_id: int|null}> */
    private function menus(): array
    {
        /** @var list<array{id: int, key_name: string, parent_id: int|null}> $menus */
        $menus = new ReflectionClassConstant(MenuSeedService::class, 'MENUS')->getValue();

        return $menus;
    }

    /** @return array<int, string> menu id => key name, as the application seeds it */
    private function seededMenus(): array
    {
        return array_column($this->menus(), 'key_name', 'id');
    }

    public function testEveryHiddenEntryIsAMenuTheApplicationActuallySeeds(): void
    {
        $unknown = array_values(array_diff(MinimarketMenu::HIDDEN, array_keys($this->seededMenus())));

        self::assertSame([], $unknown, 'ids that no longer exist in MenuSeedService');
    }

    public function testTheShopKeepsEverythingItSellsAndCountsWith(): void
    {
        $hidden = array_map(fn(int $id): string => $this->seededMenus()[$id], MinimarketMenu::HIDDEN);

        foreach ([
            'Inicio',
            'Punto de venta',
            'Facturación',
            'Caja',
            'Clientes',
            'Catálogo',
            'Inventario',
            'Compras',
            'Finanzas',
            'Cuentas por cobrar',
            'Cuentas por pagar',
            'Reportes financieros',
            'Usuarios',
            'Actividad',
        ] as $kept) {
            self::assertNotContains($kept, $hidden, $kept . ' belongs in the demo');
        }
        self::assertContains('Restaurante', $hidden);
    }

    public function testAHiddenGroupNeverLeavesAVisibleChildBehind(): void
    {
        foreach ($this->menus() as $menu) {
            if ($menu['parent_id'] !== null && in_array($menu['parent_id'], MinimarketMenu::HIDDEN, true)) {
                self::assertContains($menu['id'], MinimarketMenu::HIDDEN, $menu['key_name'] . ' would be orphaned');
            }
        }
    }
}
