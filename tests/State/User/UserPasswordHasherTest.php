<?php

declare(strict_types=1);

namespace App\Tests\State\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\State\UserPasswordHasher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserPasswordHasherTest extends TestCase
{
    public function testPostWithoutPlainPasswordThrowsValidationExceptionBeforePersistence(): void
    {
        [$processor, $persistProcessor, $passwordHasher] = $this->createPasswordHasherProcessor();
        $user = $this->createUser();

        $persistProcessor->expects(self::never())->method('process');
        $passwordHasher->expects(self::never())->method('hashPassword');

        try {
            $processor->process($user, new Post(), [], []);
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $violations = iterator_to_array($exception->getConstraintViolationList());

            self::assertCount(1, $violations);
            self::assertSame('plainPassword', $violations[0]->getPropertyPath());
            self::assertSame('This value should not be blank.', $violations[0]->getMessage());
        }
    }

    public function testPostWithEmptyPlainPasswordThrowsValidationExceptionBeforePersistence(): void
    {
        [$processor, $persistProcessor, $passwordHasher] = $this->createPasswordHasherProcessor();
        $user = $this->createUser()->setPlainPassword('');

        $persistProcessor->expects(self::never())->method('process');
        $passwordHasher->expects(self::never())->method('hashPassword');

        $this->expectException(ValidationException::class);

        $processor->process($user, new Post(), [], []);
    }

    public function testPostWithPlainPasswordHashesPasswordAndClearsCredentials(): void
    {
        [$processor, $persistProcessor, $passwordHasher] = $this->createPasswordHasherProcessor();
        $user = $this->createUser()->setPlainPassword('secret123');

        $passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn('hashed-secret');

        $persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with($user, self::isInstanceOf(Post::class), [], [])
            ->willReturn($user);

        $result = $processor->process($user, new Post(), [], []);

        self::assertSame($user, $result);
        self::assertSame('hashed-secret', $user->getPassword());
        self::assertNull($user->getPlainPassword());
    }

    public function testUpdateWithoutPlainPasswordKeepsExistingPassword(): void
    {
        [$processor, $persistProcessor, $passwordHasher] = $this->createPasswordHasherProcessor();
        $user = $this->createUser()->setPassword('existing-hash');

        $passwordHasher->expects(self::never())->method('hashPassword');
        $persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with($user, self::isInstanceOf(Patch::class), ['id' => 10], [])
            ->willReturn($user);

        $result = $processor->process($user, new Patch(), ['id' => 10], []);

        self::assertSame($user, $result);
        self::assertSame('existing-hash', $user->getPassword());
        self::assertNull($user->getPlainPassword());
    }

    public function testUserApiResourceConfiguresPasswordValidationAndHasherPerOperation(): void
    {
        $resource = (new ReflectionClass(User::class))->getAttributes(ApiResource::class)[0]->newInstance();

        $postOperation = null;
        $putOperation = null;
        $patchOperation = null;

        foreach ($resource->getOperations() ?? [] as $operation) {
            if ($operation instanceof Post) {
                $postOperation = $operation;
            }

            if ($operation instanceof Put) {
                $putOperation = $operation;
            }

            if ($operation instanceof Patch) {
                $patchOperation = $operation;
            }
        }

        self::assertInstanceOf(Post::class, $postOperation);
        self::assertSame(UserPasswordHasher::class, $postOperation->getProcessor());
        self::assertSame(['groups' => ['Default', 'user:create']], $postOperation->getValidationContext());
        self::assertInstanceOf(Put::class, $putOperation);
        self::assertSame(UserPasswordHasher::class, $putOperation->getProcessor());
        self::assertInstanceOf(Patch::class, $patchOperation);
        self::assertSame(UserPasswordHasher::class, $patchOperation->getProcessor());

        $plainPassword = new ReflectionClass(User::class)->getProperty('plainPassword');
        $attributes = $plainPassword->getAttributes();
        $constraintClasses = array_map(static fn ($attribute): string => $attribute->getName(), $attributes);

        self::assertContains(NotBlank::class, $constraintClasses);
        self::assertContains(Length::class, $constraintClasses);

        $notBlank = $plainPassword->getAttributes(NotBlank::class)[0]->newInstance();
        $length = $plainPassword->getAttributes(Length::class)[0]->newInstance();

        self::assertSame(['user:create'], $notBlank->groups);
        self::assertSame(6, $length->min);
        self::assertSame(255, $length->max);
    }

    private function createUser(): User
    {
        $role = new Role();
        $user = new User();
        $user->setUsername('secure-user');
        $user->setPassword('existing-password-hash');
        $user->setRole($role);

        return $user;
    }

    /**
     * @return array{0: UserPasswordHasher, 1: ProcessorInterface<mixed, mixed>&MockObject, 2: UserPasswordHasherInterface&MockObject}
     */
    private function createPasswordHasherProcessor(): array
    {
        /** @var ProcessorInterface<mixed, mixed>&MockObject $persistProcessor */
        $persistProcessor = $this->createMock(ProcessorInterface::class);
        /** @var UserPasswordHasherInterface&MockObject $passwordHasher */
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        return [new UserPasswordHasher($persistProcessor, $passwordHasher), $persistProcessor, $passwordHasher];
    }
}
