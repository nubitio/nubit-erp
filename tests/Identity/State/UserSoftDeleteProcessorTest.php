<?php

declare(strict_types=1);

namespace App\Tests\Identity\State;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\State\UserSoftDeleteProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UserSoftDeleteProcessorTest extends TestCase
{
    /** @var ProcessorInterface<User, null>&MockObject */
    private ProcessorInterface&MockObject $persistProcessor;

    public function testSoftDeleteSetsDeletedAtAndPersistsUser(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $user = $this->createUser();

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with(
                self::callback(static function (User $processedUser): bool {
                    return $processedUser->getDeletedAt() instanceof DateTimeImmutable;
                }),
                self::isInstanceOf(Delete::class),
                ['id' => 99],
                ['request_uri' => '/api/users/99'],
            );

        $processor = new UserSoftDeleteProcessor($this->persistProcessor);

        $processor->process($user, new Delete(), ['id' => 99], ['request_uri' => '/api/users/99']);
        self::assertInstanceOf(DateTimeImmutable::class, $user->getDeletedAt());
    }

    public function testSoftDeleteKeepsExistingDeletedAtTimestamp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $deletedAt = new DateTimeImmutable('-1 day');
        $user = $this->createUser()->setDeletedAt($deletedAt);

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with($user, self::isInstanceOf(Delete::class), [], []);

        $processor = new UserSoftDeleteProcessor($this->persistProcessor);

        $processor->process($user, new Delete(), [], []);

        self::assertSame($deletedAt, $user->getDeletedAt());
    }

    public function testSoftDeleteIsIdempotentAcrossRepeatedDeleteRequests(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $user = $this->createUser();

        $this->persistProcessor
            ->expects(self::exactly(2))
            ->method('process')
            ->with($user, self::isInstanceOf(Delete::class), ['id' => 99], []);

        $processor = new UserSoftDeleteProcessor($this->persistProcessor);

        $processor->process($user, new Delete(), ['id' => 99], []);
        $firstDeletedAt = $user->getDeletedAt();
        $processor->process($user, new Delete(), ['id' => 99], []);

        self::assertInstanceOf(DateTimeImmutable::class, $firstDeletedAt);
        self::assertSame($firstDeletedAt, $user->getDeletedAt());
    }

    public function testUserDeleteOperationUsesSoftDeleteProcessorAndNoOutput(): void
    {
        $resource = new ReflectionClass(User::class)->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if (!$operation instanceof Delete) {
                continue;
            }

            self::assertSame(UserSoftDeleteProcessor::class, $operation->getProcessor());
            self::assertFalse($operation->getOutput());

            return;
        }

        self::fail('User ApiResource is missing its DELETE operation.');
    }

    private function createUser(): User
    {
        $role = new Role();
        $user = new User();
        $user->setUsername('soft-delete-user');
        $user->setPassword('hashed-password');
        $user->setRole($role);

        return $user;
    }
}
