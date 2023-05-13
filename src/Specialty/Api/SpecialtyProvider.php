<?php

namespace App\Specialty\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Specialty\Domain\GetAllSpecialtyUseCase;
use App\Specialty\Domain\GetOneSpecialtyUseCase;
use App\Specialty\Domain\Specialty;
use App\Specialty\Infrastructure\SpecialtyEntity;

/**
 * @implements ApiProviderInterface<Specialty>
 */
readonly class SpecialtyProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllSpecialtyUseCase $getAllUseCase,
        private GetOneSpecialtyUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, SpecialtyEntity::class, Specialty::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
