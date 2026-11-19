<?php

declare(strict_types=1);

namespace App\Tests\Settings\State;

use App\Settings\Entity\Menu;
use App\Settings\State\MenuProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

final class MenuProviderTest extends TestCase
{
    public function testBuildsHeadingsWithTheirChildRoutes(): void
    {
        $heading = $this->menu(1, 'Ventas', null, null);
        $child = $this->menu(2, 'Comprobantes', 'billing', $heading);
        $direct = $this->menu(3, 'Configuracion', 'config', null);

        $query = $this->createMock(Query::class);
        $query->expects(static::once())->method('getResult')->willReturn([$heading, $child, $direct]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(static::once())
            ->method('createQuery')
            ->with(static::stringContains('LEFT JOIN m.parent p'))
            ->willReturn($query);

        $result = iterator_to_array(new MenuProvider($entityManager)->buildMenuForRole(0));

        static::assertCount(2, $result);
        static::assertSame('Ventas', $result[0]->text);
        static::assertSame('/billing', $result[0]->items[0]->path);
        static::assertSame('/config', $result[1]->path);
    }

    public function testRoleQueryUsesDqlMenuRoleJoin(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects(static::once())->method('setParameter')->with('roleId', 4)->willReturnSelf();
        $query->expects(static::once())->method('getResult')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(static::once())
            ->method('createQuery')
            ->with(static::stringContains('INNER JOIN App\\Settings\\Entity\\MenuRol mr WITH mr.menu = m'))
            ->willReturn($query);

        static::assertSame([], new MenuProvider($entityManager)->getMenuForRole(4));
    }

    private function menu(int $id, string $name, ?string $route, ?Menu $parent): Menu
    {
        $menu = new Menu()
            ->setKeyName($name)
            ->setType('menu')
            ->setRoute($route)
            ->setParent($parent);
        new \ReflectionProperty(Menu::class, 'id')->setValue($menu, $id);

        return $menu;
    }
}
