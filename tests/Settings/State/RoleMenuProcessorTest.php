<?php

declare(strict_types=1);

namespace App\Tests\Settings\State;

use ApiPlatform\Metadata\Post;
use App\Identity\Entity\Role;
use App\Settings\ApiResource\RoleMenuResource;
use App\Settings\Entity\Menu;
use App\Settings\Entity\MenuRol;
use App\Settings\State\RoleMenuProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

final class RoleMenuProcessorTest extends TestCase
{
    public function testReplacesRoleMenusWithDqlDeleteAndManagedEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method('commit');
        $connection->expects(static::never())->method('rollBack');

        $delete = $this->createMock(Query::class);
        $delete->expects(static::once())->method('setParameter')->with('roleId', 5)->willReturnSelf();
        $delete->expects(static::once())->method('execute');

        $role = new Role();
        $menu = new Menu();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager
            ->expects(static::once())
            ->method('createQuery')
            ->with(static::stringContains('DELETE FROM App\\Settings\\Entity\\MenuRol'))
            ->willReturn($delete);
        $entityManager
            ->expects(static::exactly(3))
            ->method('getReference')
            ->willReturnCallback(static fn (string $class): Role|Menu => $class === Role::class ? $role : $menu);
        $entityManager
            ->expects(static::exactly(2))
            ->method('persist')
            ->with(static::callback(static fn (MenuRol $menuRole): bool => $menuRole->getRole() === $role && $menuRole->getMenu() === $menu));
        $entityManager->expects(static::once())->method('flush');

        $response = (new RoleMenuProcessor($entityManager))->process(
            new RoleMenuResource(5, [10, 20]),
            new Post(),
        );

        static::assertTrue($response->success);
    }

    public function testRollsBackWhenDeletingExistingAssignmentsFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method('rollBack');
        $connection->expects(static::never())->method('commit');

        $delete = $this->createStub(Query::class);
        $delete->method('setParameter')->willReturnSelf();
        $delete->method('execute')->willThrowException(new \RuntimeException('failure'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('createQuery')->willReturn($delete);
        $entityManager->expects(static::never())->method('persist');

        $response = (new RoleMenuProcessor($entityManager))->process(new RoleMenuResource(5, [10]), new Post());

        static::assertFalse($response->success);
    }
}
