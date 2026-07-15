<?php

declare(strict_types=1);

namespace App\Tests\Identity\State;

use ApiPlatform\Metadata\Post;
use App\Identity\ApiResource\RolePermissionResource;
use App\Identity\Entity\Role;
use App\Identity\Entity\RolePermission;
use App\Identity\State\RolePermissionProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

final class RolePermissionProcessorTest extends TestCase
{
    public function testReplacesPermissionsWithDqlDeleteAndManagedEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $delete = $this->createMock(Query::class);
        $delete->expects(self::once())->method('setParameter')->with('roleId', 5)->willReturnSelf();
        $delete->expects(self::once())->method('execute');

        $role = new Role();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager
            ->expects(self::once())
            ->method('createQuery')
            ->with(static::stringContains('DELETE FROM App\\Identity\\Entity\\RolePermission'))
            ->willReturn($delete);
        $entityManager->expects(self::once())->method('getReference')->with(Role::class, 5)->willReturn($role);
        $entityManager
            ->expects(self::exactly(2))
            ->method('persist')
            ->with(static::callback(static fn(RolePermission $permission): bool => $permission->getRole() === $role));
        $entityManager->expects(self::once())->method('flush');

        $processor = new RolePermissionProcessor($entityManager);
        $response = $processor->process(new RolePermissionResource(5, [
            'sales.view',
            'sales.view',
            'not.allowed',
            'cash.open',
        ]), new Post());

        static::assertTrue($response->success);
    }

    public function testReturnsFailureAndRollsBackWhenPersistenceFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');

        $delete = $this->createStub(Query::class);
        $delete->method('setParameter')->willReturnSelf();
        $delete->method('execute')->willThrowException(new \RuntimeException('failure'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('createQuery')->willReturn($delete);
        $entityManager->expects(self::never())->method('persist');

        $processor = new RolePermissionProcessor($entityManager);
        $response = $processor->process(new RolePermissionResource(5, ['sales.view']), new Post());

        static::assertFalse($response->success);
    }
}
