<?php

namespace App\ComboOption\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\ComboOption\Domain\ComboOption;
use App\ComboOption\Domain\GetAllComboOptionUseCase;
use App\ComboOption\Domain\GetOneComboOptionUseCase;
use App\ComboOption\Infrastructure\ComboOptionEntity;

/**
 * @implements ApiProviderInterface<ComboOption>
 */
readonly class ComboOptionProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllComboOptionUseCase $getAllUseCase,
        private GetOneComboOptionUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('_key', 'asc'));
            $options = OptionsBuilder::build($context, ComboOptionEntity::class, ComboOption::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}