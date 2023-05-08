<?php

namespace App\PatientType\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\PatientType\Domain\PatientType;
use App\PatientType\Domain\GetAllPatientTypeUseCase;
use App\PatientType\Domain\GetOnePatientTypeUseCase;
use App\PatientType\Infrastructure\PatientTypeEntity;

/**
 * @implements ApiProviderInterface<PatientType>
 */
readonly class PatientTypeProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPatientTypeUseCase $getAllUseCase,
        private GetOnePatientTypeUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, PatientTypeEntity::class, PatientType::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}