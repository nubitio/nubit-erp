<?php

declare(strict_types=1);

namespace App\Tests\Identity\Activity;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\ApiResource\RolePermissionResource;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\State\RolePermissionProcessor;
use App\Identity\State\UserPasswordHasher;
use App\Identity\State\UserSoftDeleteProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** The places that write to the activity record: what they say, and when they stay silent. */
final class ActivityInstrumentationTest extends TestCase
{
    public function testChangingRolePermissionsRecordsWhatWasAddedAndRemoved(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder
            ->expects(self::once())
            ->method('record')
            ->with(
                'role.permissions_changed',
                'Se cambiaron los permisos del rol Cajero',
                self::logicalAnd(self::stringContains('Agregó: '), self::stringContains('Quitó: ')),
                'Cajero',
            );

        $this->rolePermissions($recorder, before: ['sales.view', 'sales.void'])->process(
            new RolePermissionResource(3, ['sales.view', 'sales.create']),
            new Post(),
        );
    }

    public function testSavingTheSamePermissionsRecordsNothing(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder->expects(self::never())->method('record');

        $this->rolePermissions($recorder, before: ['sales.view'])->process(
            new RolePermissionResource(3, ['sales.view']),
            new Post(),
        );
    }

    public function testLongChangesListAFewLabelsAndCountTheRest(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder
            ->expects(self::once())
            ->method('record')
            ->with(self::anything(), self::anything(), self::stringContains(' y 2 más'));

        $this->rolePermissions($recorder, before: [])->process(new RolePermissionResource(3, [
            'sales.view',
            'sales.create',
            'sales.void',
            'sales.export',
            'sales.collect',
        ]), new Post());
    }

    public function testCreatingAUserIsRecordedButEditingOneIsNot(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder
            ->expects(self::once())
            ->method('record')
            ->with('user.created', 'Se creó el usuario lucia', 'Rol: Cajero', 'lucia');

        $processor = new UserPasswordHasher($this->createStub(ProcessorInterface::class), $this->hasher(), $recorder);
        $processor->process($this->user('lucia', 'secret-123'), new Post());
        $processor->process($this->user('lucia', 'secret-456'), new Patch());
    }

    public function testDeletingAUserIsRecordedOnlyTheFirstTime(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder
            ->expects(self::once())
            ->method('record')
            ->with('user.deleted', 'Se eliminó al usuario diego', null, 'diego');
        $processor = new UserSoftDeleteProcessor($this->createStub(ProcessorInterface::class), $recorder);
        $user = $this->user('diego', null);

        $processor->process($user, new Delete());
        $processor->process($user, new Delete());
    }

    /** @param list<string> $before */
    private function rolePermissions(ActivityRecorderInterface $recorder, array $before): RolePermissionProcessor
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($before);
        $query = $this->createStub(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('createQuery')->willReturn($query);
        $entityManager->method('getReference')->willReturn(new Role()->setName('Cajero'));

        return new RolePermissionProcessor($entityManager, $recorder);
    }

    private function user(string $username, #[\SensitiveParameter] ?string $plainPassword): User
    {
        return new User()
            ->setUsername($username)
            ->setRole(new Role()->setName('Cajero'))
            ->setPlainPassword($plainPassword);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        return $hasher;
    }
}
