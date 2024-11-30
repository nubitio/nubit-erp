<?php

namespace App\Department\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Department\Domain\Department;
use App\Department\Domain\GetAllDepartmentUseCase;
use App\Department\Domain\GetOneDepartmentUseCase;
use App\Department\Infrastructure\DepartmentEntity;

/**
 * @implements ApiProviderInterface<Department>
 */
readonly class DepartmentProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllDepartmentUseCase $getAllUseCase,
        private GetOneDepartmentUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, DepartmentEntity::class, Department::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
