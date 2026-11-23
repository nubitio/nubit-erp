<?php

declare(strict_types=1);

namespace App\Tests\Settings\Service;

use App\Settings\Service\MenuSeedService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Runs the real menu seed against the schema of the migrations. */
final class MenuSeedServiceRoleMenusIntegrationTest extends KernelTestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    private function connection(): Connection
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $this->connection = $connection;
    }

    /** @return list<int> */
    private function menusOf(Connection $db, int $roleId): array
    {
        return array_map(
            intval(...),
            $db->fetchFirstColumn('SELECT menu_id FROM menu_rol WHERE role_id = ? ORDER BY menu_id', [$roleId]),
        );
    }

    private function ensureRole(Connection $db, int $roleId, string $name): void
    {
        $db->executeStatement('INSERT INTO rol (rol_id, descripcion) VALUES (?, ?) ON DUPLICATE KEY UPDATE descripcion = descripcion', [
            $roleId,
            $name,
        ]);
    }

    public function testTheCashierSeesOnlyThePointOfSaleAndEveryoneElseTheWholeMenu(): void
    {
        $db = $this->connection();
        $db->beginTransaction();
        $this->ensureRole($db, 2, 'Administrador');
        $this->ensureRole($db, 3, 'Cajero');

        new MenuSeedService($db)->seed();

        self::assertSame([MenuSeedService::POS_MENU_ID], $this->menusOf($db, MenuSeedService::CASHIER_ROLE_ID));
        $all = array_map(intval(...), $db->fetchFirstColumn('SELECT id FROM menu ORDER BY id'));
        self::assertGreaterThan(1, count($all));
        self::assertSame($all, $this->menusOf($db, 2));
    }

    public function testSyncingNeverBackfillsTheCashierWithOtherMenus(): void
    {
        $db = $this->connection();
        $db->beginTransaction();
        $this->ensureRole($db, 2, 'Administrador');
        $this->ensureRole($db, 3, 'Cajero');
        $service = new MenuSeedService($db);
        $service->seed();
        $db->executeStatement('DELETE FROM menu_rol WHERE role_id IN (2, 3)');

        $service->syncMvpPreservingRoleAccess();

        self::assertSame([MenuSeedService::POS_MENU_ID], $this->menusOf($db, 3), 'the cashier gets its own menu back');
        self::assertGreaterThan(1, count($this->menusOf($db, 2)), 'other roles are backfilled as before');
    }

    public function testTheCashierMenuEntryIsThePointOfSale(): void
    {
        $db = $this->connection();
        $db->beginTransaction();
        $this->ensureRole($db, 3, 'Cajero');
        new MenuSeedService($db)->seed();

        self::assertSame('/pos', $db->fetchOne('SELECT route FROM menu WHERE id = ?', [MenuSeedService::POS_MENU_ID]));
    }
}
