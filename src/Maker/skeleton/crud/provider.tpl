<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\{{module}}\Domain\{{module}};
use App\{{module}}\Domain\GetAll{{module}}UseCase;
use App\{{module}}\Domain\GetOne{{module}}UseCase;
use App\{{module}}\Infrastructure\{{module}}Entity;

/**
 * @implements ApiProviderInterface<{{module}}|PaginatorInterface>
 */
readonly class {{module}}Provider implements ApiProviderInterface
{
    public function __construct(
        private GetAll{{module}}UseCase $getAllUseCase,
        private GetOne{{module}}UseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('{{sortColumn}}', '{{sortOrder}}'));
            $options = OptionsBuilder::build($context, {{module}}Entity::class, {{module}}::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['{{entityId}}']);
    }
}