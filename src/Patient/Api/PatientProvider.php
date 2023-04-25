<?php

namespace App\Patient\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\JoinType;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Patient\Domain\Patient;
use App\Patient\Domain\GetAllPatientUseCase;
use App\Patient\Domain\GetOnePatientUseCase;
use App\Patient\Infrastructure\PatientEntity;

/**
 * @implements ApiProviderInterface<Patient>
 */
readonly class PatientProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPatientUseCase $getAllUseCase,
        private GetOnePatientUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.customer', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('c.price_list', 'p', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('c.razon_social', 'asc'));
            $options = OptionsBuilder::build($context, PatientEntity::class, Patient::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}