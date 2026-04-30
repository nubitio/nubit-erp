<?php

declare(strict_types=1);

namespace App\Tests\Core\Security\Auth;

use App\Identity\Auth\UserProvider;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserProviderTest extends TestCase
{
    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private EntityManagerInterface $entityManager;

    /** @var EntityRepository<User> */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createStub(EntityRepository::class);
    }

    public function testLoadUserByIdentifierReturnsActiveUser(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($this->repository);
        $user = $this->createUser('active-user');

        $this->repository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'username' => 'active-user',
                'deletedAt' => null,
            ])
            ->willReturn($user);

        self::assertSame($user, $this->createProvider()->loadUserByIdentifier('active-user'));
    }

    public function testLoadUserByIdentifierRejectsSoftDeletedUser(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($this->repository);
        $this->repository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'username' => 'deleted-user',
                'deletedAt' => null,
            ])
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('');

        $this->createProvider()->loadUserByIdentifier('deleted-user');
    }

    public function testRefreshUserRejectsSoftDeletedUserWithGenericFailure(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($this->repository);
        $activeUser = $this->createUser('refresh-deleted-user');

        $this->repository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'username' => 'refresh-deleted-user',
                'deletedAt' => null,
            ])
            ->willReturn(null);

        try {
            $this->createProvider()->refreshUser($activeUser->setDeletedAt(new DateTimeImmutable('-1 hour')));
            self::fail('Expected a UserNotFoundException for soft-deleted users.');
        } catch (UserNotFoundException $exception) {
            self::assertSame('', $exception->getMessage());
            self::assertStringNotContainsString('refresh-deleted-user', $exception->getMessage());
        }
    }

    public function testRefreshUserReloadsByIdentifier(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($this->repository);
        $user = $this->createUser('refresh-user');

        $this->repository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'username' => 'refresh-user',
                'deletedAt' => null,
            ])
            ->willReturn($user);

        self::assertSame($user, $this->createProvider()->refreshUser($user));
    }

    public function testRefreshUserRejectsUnsupportedUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $unsupportedUser = new class () extends stdClass implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'unsupported-user';
            }
        };

        $this->createProvider()->refreshUser($unsupportedUser);
    }

    private function createUser(string $username): User
    {
        $role = new Role();

        return (new User())
            ->setUsername($username)
            ->setPassword('hashed-password')
            ->setRole($role)
            ->setDeletedAt(null);
    }

    private function createProvider(): UserProvider
    {
        return new UserProvider($this->entityManager);
    }
}
