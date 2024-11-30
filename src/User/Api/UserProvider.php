<?php

namespace App\User\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\User\Domain\GetAllUserUseCase;
use App\User\Domain\GetOneUserUseCase;
use App\User\Domain\User;
use App\User\Infrastructure\UserEntity;

/**
 * @implements ApiProviderInterface<User>
 */
readonly class UserProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllUserUseCase $getAllUseCase,
        private GetOneUserUseCase $getOneUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.rol', 'r'));
            $context = OptionUtils::addSort($context, new SortRule('username', 'asc'));
            $options = OptionsBuilder::build($context, UserEntity::class, User::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['usuario_id']));
    }
}
