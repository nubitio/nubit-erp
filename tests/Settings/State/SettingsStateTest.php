<?php

declare(strict_types=1);

namespace App\Tests\Settings\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Settings\ApiResource\RoleMenuResource;
use App\Settings\Dto\RoleMenuDto;
use App\Settings\Dto\RoleMenuItemDto;
use App\Settings\State\MenuProvider;
use App\Settings\State\RoleMenuProcessor;
use App\Settings\State\RoleMenuProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException as DbalException;
use PHPUnit\Framework\TestCase;

final class SettingsStateTest extends TestCase
{
    // ── RoleMenuProvider ──────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private static function menuRows(): array
    {
        return [
            ['menu_id' => 1, 'descripcion' => 'Ventas', 'parent_id' => 0, 'checked' => 1],
            ['menu_id' => 2, 'descripcion' => 'Facturación', 'parent_id' => 1, 'checked' => 0],
            ['menu_id' => 3, 'descripcion' => 'Reportes', 'parent_id' => 1, 'checked' => 1],
            ['menu_id' => 4, 'descripcion' => 'Inventario', 'parent_id' => 0, 'checked' => 0],
        ];
    }

    public function testRoleMenuProviderGroupsChildrenUnderParents(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn(self::menuRows());

        $provider = new RoleMenuProvider($conn);
        $result = $provider->provide(new GetCollection(), ['role_id' => 2]);

        self::assertIsArray($result);
        self::assertCount(2, $result); // 2 root nodes (parent_id=0)

        /** @var RoleMenuDto $ventas */
        $ventas = $result[0];
        self::assertInstanceOf(RoleMenuDto::class, $ventas);
        self::assertSame(1, $ventas->id);
        self::assertSame('Ventas', $ventas->text);
        self::assertCount(2, $ventas->items); // children menu_id 2 and 3
    }

    public function testRoleMenuProviderChildrenHaveCorrectSelectedState(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn(self::menuRows());

        $provider = new RoleMenuProvider($conn);
        $result = $provider->provide(new GetCollection(), ['role_id' => 2]);

        /** @var RoleMenuDto $ventas */
        $ventas = $result[0];
        /** @var RoleMenuItemDto $facturacion */
        $facturacion = $ventas->items[0];
        $reportes = $ventas->items[1];

        self::assertFalse($facturacion->selected); // checked=0
        self::assertTrue($reportes->selected); // checked=1
    }

    public function testRoleMenuProviderRootWithNoChildrenHasEmptyItems(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn(self::menuRows());

        $provider = new RoleMenuProvider($conn);
        $result = $provider->provide(new GetCollection(), ['role_id' => 2]);

        /** @var RoleMenuDto $inventario */
        $inventario = $result[1];
        self::assertSame('Inventario', $inventario->text);
        self::assertCount(0, $inventario->items);
    }

    // ── MenuProvider::buildMenuForRole ────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private static function rawMenuRows(): array
    {
        return [
            ['id' => 1, 'key_name' => 'app.sales', 'parent_id' => 0, 'icon' => 'fa-cart', 'route' => ''],
            ['id' => 2, 'key_name' => 'app.sales.list', 'parent_id' => 1, 'icon' => '', 'route' => '/ventas'],
            ['id' => 3, 'key_name' => 'app.sales.create', 'parent_id' => 1, 'icon' => '', 'route' => '/ventas/nueva'],
            ['id' => 4, 'key_name' => 'app.dashboard', 'parent_id' => 0, 'icon' => 'fa-home', 'route' => '/dashboard'],
        ];
    }

    public function testMenuProviderBuildMenuForRoleGroupsChildren(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn(self::rawMenuRows());

        $provider = new MenuProvider($conn);
        $menu = iterator_to_array($provider->buildMenuForRole(1));

        // 'app.sales' (parent, no route → group) + 'app.dashboard' (parent with route → link)
        self::assertCount(2, $menu);
    }

    public function testMenuProviderBuildGroupHasChildren(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn(self::rawMenuRows());

        $provider = new MenuProvider($conn);
        $menu = iterator_to_array($provider->buildMenuForRole(1));

        $salesGroup = $menu[0];
        self::assertSame('app.sales', $salesGroup->text);
        self::assertCount(2, $salesGroup->items);
        self::assertSame('app.sales.list', $salesGroup->items[0]->text);
        self::assertSame('/ventas', $salesGroup->items[0]->path);
    }

    public function testMenuProviderBuildLinkNodeHasNoChildren(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn(self::rawMenuRows());

        $provider = new MenuProvider($conn);
        $menu = iterator_to_array($provider->buildMenuForRole(1));

        $dashboard = $menu[1];
        self::assertSame('app.dashboard', $dashboard->text);
        self::assertSame('/dashboard', $dashboard->path);
        self::assertCount(0, $dashboard->items);
    }

    public function testMenuProviderRoleIdZeroFetchesAllMenus(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('FROM menu m'))
            ->willReturn([]);

        $provider = new MenuProvider($conn);
        $result = iterator_to_array($provider->buildMenuForRole(0));

        self::assertSame([], $result);
    }

    public function testMenuProviderNonZeroRoleIdJoinsMenuRol(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('menu_rol'), [7])
            ->willReturn([]);

        $provider = new MenuProvider($conn);
        iterator_to_array($provider->buildMenuForRole(7));
    }

    // ── RoleMenuProcessor ─────────────────────────────────────────────────────

    /** @param int[] $permissions */
    private function makeRoleMenuResource(int $roleId = 5, array $permissions = [10, 20]): RoleMenuResource
    {
        return new RoleMenuResource($roleId, $permissions);
    }

    public function testRoleMenuProcessorDeletesAndInsertsPermissions(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        // 1 DELETE + 2 INSERTs
        $conn->expects(self::exactly(3))->method('executeStatement');
        $conn->expects(self::once())->method('commit');
        $conn->expects(self::never())->method('rollBack');

        $processor = new RoleMenuProcessor($conn);
        $response = $processor->process($this->makeRoleMenuResource(), new Post());

        self::assertTrue($response->success);
        self::assertStringContainsString('correctamente', $response->message);
    }

    public function testRoleMenuProcessorRollsBackOnException(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        $conn->method('executeStatement')->willThrowException(new DbalException('fail'));
        $conn->expects(self::never())->method('commit');
        $conn->expects(self::once())->method('rollBack');

        $processor = new RoleMenuProcessor($conn);
        $response = $processor->process($this->makeRoleMenuResource(), new Post());

        self::assertFalse($response->success);
        self::assertStringContainsString('Error', $response->message);
    }

    public function testRoleMenuProcessorEmptyPermissionsOnlyDeletes(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        // only 1 DELETE, no INSERTs
        $conn->expects(self::once())->method('executeStatement');
        $conn->expects(self::once())->method('commit');

        $processor = new RoleMenuProcessor($conn);
        $response = $processor->process($this->makeRoleMenuResource(5, []), new Post());

        self::assertTrue($response->success);
    }
}
