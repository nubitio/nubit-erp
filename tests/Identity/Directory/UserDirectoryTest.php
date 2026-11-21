<?php

declare(strict_types=1);

namespace App\Tests\Identity\Directory;

use App\Identity\Directory\DirectoryQuery;
use App\Identity\Directory\UserDirectory;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Invitation\PendingInvitations;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use PHPUnit\Framework\TestCase;

/** The users' side of the directory, against a real (in-memory) database and the real grid filter. */
final class UserDirectoryTest extends TestCase
{
    private UserDirectory $directory;

    protected function setUp(): void
    {
        $entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            ORMSetup::createAttributeMetadataConfiguration([
                dirname(__DIR__, levels: 3) . '/src/Identity/Entity',
            ], isDevMode: true),
        );
        new SchemaTool($entityManager)->createSchema([
            $entityManager->getClassMetadata(Role::class),
            $entityManager->getClassMetadata(User::class),
        ]);

        $cashier = new Role()->setName('Cajero');
        $admin = new Role()->setName('Administrador');
        $entityManager->persist($cashier);
        $entityManager->persist($admin);
        foreach ([['ana', $cashier], ['beto', $admin], ['carla', $cashier], ['dani', $cashier]] as [$name, $role]) {
            $entityManager->persist(
                new User()
                    ->setUsername($name)
                    ->setPassword('x')
                    ->setRole($role),
            );
        }
        $entityManager->flush();

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $this->directory = new UserDirectory(
            $entityManager,
            new DataGridFilter($registry),
            new PendingInvitations($entityManager),
        );
    }

    public function testListsEveryUserAsActiveWithItsRole(): void
    {
        $page = $this->directory->page(new DirectoryQuery());

        static::assertSame(4, $page['totalItems']);
        static::assertSame(['ana', 'beto', 'carla', 'dani'], array_column($page['member'], 'username'));
        static::assertSame('active', $page['member'][0]['status']);
        $role = $page['member'][0]['role'];
        static::assertIsArray($role);
        static::assertSame('Cajero', $role['name']);
    }

    public function testPagesWithAStableOrderAndAnExactTotal(): void
    {
        $second = $this->directory->page(new DirectoryQuery(page: 2, perPage: 3));

        static::assertSame(4, $second['totalItems']);
        static::assertSame(['dani'], array_column($second['member'], 'username'));
    }

    public function testFiltersAndSortsOnTheServer(): void
    {
        $page = $this->directory->page(new DirectoryQuery(leaves: [['username', 'contains', 'a']], sort: [[
            'selector' => 'username',
            'desc' => true,
        ]]));

        static::assertSame(['dani', 'carla', 'ana'], array_column($page['member'], 'username'));
        static::assertSame(1, $this->directory->page(new DirectoryQuery(leaves: [['role', '=', '2']]))['totalItems']);
    }

    public function testSearchesTheNamedColumns(): void
    {
        $page = $this->directory->page(new DirectoryQuery(search: 'ar', searchFields: ['username']));

        static::assertSame(['carla'], array_column($page['member'], 'username'));
    }

    public function testTheStatusFilterLeavesOutWhoIsNotWanted(): void
    {
        $invited = $this->directory->page(new DirectoryQuery(leaves: [['status', '=', 'invited']]));
        static::assertSame(['member' => [], 'totalItems' => 0], $invited);

        $active = $this->directory->page(new DirectoryQuery(leaves: [['status', '=', 'active']]));
        static::assertSame(4, $active['totalItems']);
    }
}
