<?php

declare(strict_types=1);

namespace App\State\User;

use Override;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Identity\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class UserPasswordHasher implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $processor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param User $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $plainPassword = $data->getPlainPassword();

        if ($operation instanceof Post && (null === $plainPassword || '' === $plainPassword)) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation('This value should not be blank.', null, [], $data, 'plainPassword', null),
            ]));
        }

        if (null === $plainPassword || '' === $plainPassword) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($data, $plainPassword);

        $data->setPassword($hashedPassword);
        $data->eraseCredentials();

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
