<?php

namespace App\Branch\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Branch\Domain\Branch;
use App\Branch\Domain\BranchGetAllUseCase;
use App\Branch\Domain\BranchGetOneUseCase;
use App\Branch\Infrastructure\BranchEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<Branch>
 */
readonly class BranchProvider implements ApiProviderInterface
{
    public function __construct(
        private BranchGetAllUseCase $getAllUseCase,
        private BranchGetOneUseCase $getOneUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.empresa', 'c'));
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, BranchEntity::class, Branch::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['local_id']));
    }
}