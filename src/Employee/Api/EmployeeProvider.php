<?php

namespace App\Employee\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Employee\Domain\Employee;
use App\Employee\Domain\GetAllEmployeeUseCase;
use App\Employee\Domain\GetOneEmployeeUseCase;
use App\Employee\Infrastructure\EmployeeEntity;

/**
 * @implements ApiProviderInterface<Employee>
 */
readonly class EmployeeProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllEmployeeUseCase $getAllUseCase,
        private GetOneEmployeeUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.position', 'p'));
            $context = OptionUtils::addSort($context, new SortRule('first_name', 'asc'));
            $options = OptionsBuilder::build($context, EmployeeEntity::class, Employee::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}