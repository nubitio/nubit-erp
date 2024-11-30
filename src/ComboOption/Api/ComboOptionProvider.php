<?php

namespace App\ComboOption\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\ComboOption\Domain\ComboOption;
use App\ComboOption\Domain\GetAllComboOptionUseCase;
use App\ComboOption\Domain\GetOneComboOptionUseCase;
use App\ComboOption\Infrastructure\ComboOptionEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<ComboOption>
 */
readonly class ComboOptionProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllComboOptionUseCase $getAllUseCase,
        private GetOneComboOptionUseCase $getOneUseCase,
    ) {
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
