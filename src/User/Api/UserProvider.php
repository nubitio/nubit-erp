<?php

namespace App\User\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\User\Domain\GetAllUserUseCase;
use App\User\Domain\GetOneUserUseCase;
use App\User\Infrastructure\UserEntity;

readonly class UserProvider implements ProviderInterface
{
    public function __construct(
        private GetAllUserUseCase $getAllUseCase,
        private GetOneUserUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.rol', 'r'));
            $context = OptionUtils::addSort($context, new SortRule('username', 'asc'));
            $options = OptionsBuilder::build($context, UserEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['usuario_id']);
    }
}