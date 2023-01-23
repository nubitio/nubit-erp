<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Sort;
use App\{{module}}\Domain\GetAll{{module}}UseCase;
use App\{{module}}\Domain\GetOne{{module}}UseCase;
use App\{{module}}\Infrastructure\{{module}}Entity;

readonly class {{module}}Provider implements ProviderInterface
{
    public function __construct(
        private GetAll{{module}}UseCase $getAllUseCase,
        private GetOne{{module}}UseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new Sort('{{sortColumn}}', '{{sortOrder}}'));
            $options = OptionsBuilder::build($context, {{module}}Entity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['{{entityId}}']);
    }
}