<?php

declare(strict_types=1);

namespace App\Tests\Settings\State;

use ApiPlatform\Metadata\GetCollection;
use App\Settings\Entity\Menu;
use App\Settings\State\MenuProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

final class MenuProviderTest extends TestCase
{
    public function testProviderFailsClosedWhenRuntimeConfigIsUnavailable(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('createQuery');

        static::assertSame([], new MenuProvider($entityManager)->provide(new GetCollection(), ['roleId' => 0]));
    }

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

    public function testBuildMenuFiltersRestaurantRoutesByVerticalAndCapability(): void
    {
        $restaurant = $this->menu(1, 'Restaurante', null, null);
        $tables = $this->menu(2, 'Mesas', '/restaurant/mesas', $restaurant);
        $sales = $this->menu(3, 'Cocina', '/restaurant/kds', $restaurant);
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$restaurant, $tables, $sales]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturn($query);

        $provider = new MenuProvider($entityManager);
        $retailWithPaidEntitlement = [
            'profile' => ['vertical' => 'retail'],
            'capabilities' => [
                'restaurant.table_management' => ['enabled' => true],
            ],
        ];
        static::assertSame([], iterator_to_array($provider->buildMenuForRole(0, $retailWithPaidEntitlement)));

        $restaurantWithCapability = [
            'profile' => ['vertical' => 'restaurant'],
            'capabilities' => [
                'restaurant.table_management' => ['enabled' => true],
            ],
        ];
        $visible = iterator_to_array($provider->buildMenuForRole(0, $restaurantWithCapability));
        static::assertCount(1, $visible);
        static::assertCount(2, $visible[0]->items);

        $restaurantWithoutCapability = [
            'profile' => ['vertical' => 'restaurant'],
            'capabilities' => [],
        ];
        static::assertSame([], iterator_to_array($provider->buildMenuForRole(0, $restaurantWithoutCapability)));
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
