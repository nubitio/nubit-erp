<?php

declare(strict_types=1);

namespace App\Tests\Settings\State;

use ApiPlatform\Metadata\GetCollection;
use App\Settings\Dto\RoleMenuDto;
use App\Settings\Dto\RoleMenuItemDto;
use App\Settings\Entity\Menu;
use App\Settings\State\RoleMenuProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

final class RoleMenuProviderTest extends TestCase
{
    public function testBuildsMenuTreeAndSelectedStatesFromDqlResults(): void
    {
        $parent = $this->menu(10, 'Ventas');
        $child = $this->menu(11, 'Comprobantes', $parent);
        $standalone = $this->menu(12, 'Configuracion');

        $menus = $this->createMock(Query::class);
        $menus->expects(static::once())->method('getResult')->willReturn([$parent, $child, $standalone]);

        $assignments = $this->createMock(Query::class);
        $assignments->expects(static::once())->method('setParameter')->with('roleId', 3)->willReturnSelf();
        $assignments->expects(static::once())->method('getSingleColumnResult')->willReturn(['10', '11']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(static::exactly(2))
            ->method('createQuery')
            ->willReturnOnConsecutiveCalls($menus, $assignments);

        $result = (new RoleMenuProvider($entityManager))->provide(new GetCollection(), ['role_id' => '3']);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertInstanceOf(RoleMenuDto::class, $result[0]);
        static::assertInstanceOf(RoleMenuDto::class, $result[1]);

        $ventas = $result[0];
        $configuracion = $result[1];
        static::assertSame('Ventas', $ventas->text);
        static::assertTrue($ventas->selected);
        static::assertCount(1, $ventas->items);
        static::assertInstanceOf(RoleMenuItemDto::class, $ventas->items[0]);
        static::assertSame('Comprobantes', $ventas->items[0]->text);
        static::assertTrue($ventas->items[0]->selected);
        static::assertFalse($configuracion->selected);
    }

    private function menu(int $id, string $name, ?Menu $parent = null): Menu
    {
        $menu = (new Menu())->setKeyName($name)->setType('menu')->setParent($parent);
        (new \ReflectionProperty(Menu::class, 'id'))->setValue($menu, $id);

        return $menu;
    }
}
