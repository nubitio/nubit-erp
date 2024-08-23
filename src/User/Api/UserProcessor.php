<?php

namespace App\User\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\User\Domain\CreateUserUseCase;
use App\User\Domain\DeleteUserUseCase;
use App\User\Domain\UpdateUserUseCase;
use App\User\Domain\User;

/**
 * @implements ApiProcessorInterface<User, User>
 */
readonly class UserProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateUserUseCase $createUseCase,
        public UpdateUserUseCase $updateUseCase,
        public DeleteUserUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->usuario_id) {
                $this->deleteUseCase->__invoke($data->usuario_id);
            }

            return null;
        }

        if ($data->usuario_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
