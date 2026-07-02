<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Identity\Entity\Role;
use App\Settings\Entity\Menu;
use App\Settings\Entity\MenuRol;
use App\Settings\Entity\Ubigeo;
use PHPUnit\Framework\TestCase;

final class SettingsEntitiesTest extends TestCase
{
    // ── Ubigeo ────────────────────────────────────────────────────────────────

    public function testUbigeoSettersAndGetters(): void
    {
        $u = new Ubigeo();
        $u->setCode('150101');
        $u->setDepartment('LIMA');
        $u->setProvince('LIMA');
        $u->setDistrict('LIMA');

        self::assertSame('150101', $u->getCode());
        self::assertSame('LIMA', $u->getDepartment());
        self::assertSame('LIMA', $u->getProvince());
        self::assertSame('LIMA', $u->getDistrict());
    }

    public function testUbigeoDisplayNameFullFields(): void
    {
        $u = new Ubigeo();
        $u->setCode('150101');
        $u->setDepartment('LIMA');
        $u->setProvince('LIMA');
        $u->setDistrict('CERCADO DE LIMA');

        $name = $u->getDisplayName();

        self::assertStringContainsString('LIMA', $name);
        self::assertStringContainsString('CERCADO DE LIMA', $name);
        self::assertStringContainsString('150101', $name);
    }

    public function testUbigeoDisplayNameWithNullFields(): void
    {
        $u = new Ubigeo();
        $u->setCode('150000');
        $u->setDepartment('LIMA');
        // province and district null

        $name = $u->getDisplayName();

        self::assertStringContainsString('LIMA', $name);
        self::assertStringContainsString('150000', $name);
    }

    public function testUbigeoNullableFieldsDefault(): void
    {
        $u = new Ubigeo();

        self::assertNull($u->getDepartment());
        self::assertNull($u->getProvince());
        self::assertNull($u->getDistrict());
    }

    // ── MenuRol ───────────────────────────────────────────────────────────────

    public function testMenuRolSettersAndGetters(): void
    {
        $menu = new Menu();
        $role = new Role();

        $menuRol = new MenuRol();
        $menuRol->setMenu($menu);
        $menuRol->setRole($role);

        self::assertSame($menu, $menuRol->getMenu());
        self::assertSame($role, $menuRol->getRole());
        self::assertNull($menuRol->getId());
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public function testMenuSettersAndGetters(): void
    {
        $menu = new Menu();
        $menu->setKeyName('sales.index');
        $menu->setType('link');
        $menu->setIcon('fa-shopping-cart');
        $menu->setRoute('sales_index');
        $menu->setOrderIndex(10);
        $menu->setIsActive(true);

        self::assertSame('sales.index', $menu->getKeyName());
        self::assertSame('link', $menu->getType());
        self::assertSame('fa-shopping-cart', $menu->getIcon());
        self::assertSame('sales_index', $menu->getRoute());
        self::assertSame(10, $menu->getOrderIndex());
        self::assertTrue($menu->isActive());
        self::assertNull($menu->getId());
    }

    public function testMenuParentChildRelationship(): void
    {
        $parent = new Menu();
        $parent->setKeyName('main');
        $parent->setType('group');

        $child = new Menu();
        $child->setKeyName('sub');
        $child->setType('link');
        $child->setParent($parent);
        $parent->addChild($child);

        self::assertSame($parent, $child->getParent());
        self::assertCount(1, $parent->getChildren());
    }

    public function testMenuRemoveChild(): void
    {
        $parent = new Menu();
        $child = new Menu();
        $child->setParent($parent);
        $parent->addChild($child);

        $parent->removeChild($child);

        self::assertCount(0, $parent->getChildren());
    }

    public function testMenuDefaultValues(): void
    {
        $menu = new Menu();

        self::assertNull($menu->getParent());
        self::assertCount(0, $menu->getChildren());
        self::assertTrue($menu->isActive());
        self::assertSame(0, $menu->getOrderIndex());
    }
}
