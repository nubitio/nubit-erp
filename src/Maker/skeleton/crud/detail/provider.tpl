<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\DataLoader\SortRule;
use App\{{module}}\Domain\{{module}};
use App\{{module}}\Domain\GetAll{{module}}UseCase;
use App\{{module}}\Infrastructure\{{module}}Entity;

/**
 * @implements ApiProviderInterface<{{module}}>
 */
readonly class {{module}}Provider implements ApiProviderInterface
{
    public function __construct(
        private GetAll{{module}}UseCase $getAllUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addFilter($context, new FilterRule('{{joinColumn}}', FilterOperation::Equals, $uriVariables['id']));
            $context = OptionUtils::addSort($context, new SortRule('{{sortColumn}}', '{{sortOrder}}'));
            $options = OptionsBuilder::build($context, {{module}}Entity::class, {{module}}::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
