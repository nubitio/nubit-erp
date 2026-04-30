<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Identity\Entity\Role;
use App\Settings\Dto\RoleMenuDto;
use App\Settings\Dto\RoleMenuItemDto;
use App\Settings\Dto\SubMenuDto;
use App\Settings\Entity\MenuRol;
use App\Settings\Entity\Ubigeo;
use PHPUnit\Framework\TestCase;

/**
 * Pure value-object tests for the Settings bounded context.
 */
final class SettingsDomainTest extends TestCase
{
    // ── DTOs ──────────────────────────────────────────────────────────────────

    public function testRoleMenuDtoHoldsFields(): void
    {
        $item = new RoleMenuItemDto(id: 1, text: 'Ventas', selected: true);
        $dto = new RoleMenuDto(id: 10, text: 'Principal', items: [$item]);

        self::assertSame(10, $dto->id);
        self::assertSame('Principal', $dto->text);
        self::assertCount(1, $dto->items);
        self::assertSame($item, $dto->items[0]);
    }

    public function testRoleMenuItemDtoHoldsFields(): void
    {
        $dto = new RoleMenuItemDto(id: 5, text: 'Inventario', selected: false);

        self::assertSame(5, $dto->id);
        self::assertSame('Inventario', $dto->text);
        self::assertFalse($dto->selected);
    }

    public function testSubMenuDtoHoldsFields(): void
    {
        $dto = new SubMenuDto(text: 'Productos', path: '/catalog/products');

        self::assertSame('Productos', $dto->text);
        self::assertSame('/catalog/products', $dto->path);
    }

    // ── Ubigeo ────────────────────────────────────────────────────────────────

    public function testUbigeoSettersAndGetters(): void
    {
        $ubigeo = new Ubigeo();
        $ubigeo->setCode('150101');
        $ubigeo->setDepartment('Lima');
        $ubigeo->setProvince('Lima');
        $ubigeo->setDistrict('Lima Cercado');

        self::assertSame('150101', $ubigeo->getCode());
        self::assertSame('Lima', $ubigeo->getDepartment());
        self::assertSame('Lima', $ubigeo->getProvince());
        self::assertSame('Lima Cercado', $ubigeo->getDistrict());
    }

    public function testUbigeoGetDisplayNameWithAllParts(): void
    {
        $ubigeo = new Ubigeo();
        $ubigeo->setCode('150101');
        $ubigeo->setDepartment('Lima');
        $ubigeo->setProvince('Lima');
        $ubigeo->setDistrict('Lima Cercado');

        $display = $ubigeo->getDisplayName();

        self::assertStringContainsString('Lima', $display);
        self::assertStringContainsString('Lima Cercado', $display);
        self::assertStringContainsString('150101', $display);
    }

    public function testUbigeoGetDisplayNameWithNullParts(): void
    {
        $ubigeo = new Ubigeo();
        $ubigeo->setCode('999999');
        // No department/province/district

        $display = $ubigeo->getDisplayName();

        self::assertStringContainsString('999999', $display);
    }

    public function testUbigeoNullableFieldsDefaultToNull(): void
    {
        $ubigeo = new Ubigeo();
        $ubigeo->setCode('000000');

        self::assertNull($ubigeo->getDepartment());
        self::assertNull($ubigeo->getProvince());
        self::assertNull($ubigeo->getDistrict());
    }

    public function testUbigeoFluentSetters(): void
    {
        $ubigeo = new Ubigeo();
        self::assertSame($ubigeo, $ubigeo->setCode('abc'));
        self::assertSame($ubigeo, $ubigeo->setDepartment('Dep'));
        self::assertSame($ubigeo, $ubigeo->setProvince('Prov'));
        self::assertSame($ubigeo, $ubigeo->setDistrict('Dist'));
    }

    // ── MenuRol ───────────────────────────────────────────────────────────────

    public function testMenuRolSettersAndGetters(): void
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $menu = new \App\Settings\Entity\Menu();
        $role = new Role();

        $menuRol = new MenuRol();
        $menuRol->setMenu($menu);
        $menuRol->setRole($role);

        self::assertSame($menu, $menuRol->getMenu());
        self::assertSame($role, $menuRol->getRole());
        self::assertNull($menuRol->getId());
    }

    public function testMenuRolFluentSetters(): void
    {
        $menu = new \App\Settings\Entity\Menu();
        $role = new Role();
        $menuRol = new MenuRol();

        self::assertSame($menuRol, $menuRol->setMenu($menu));
        self::assertSame($menuRol, $menuRol->setRole($role));
    }
}
