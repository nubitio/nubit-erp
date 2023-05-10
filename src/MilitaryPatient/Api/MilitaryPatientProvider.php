<?php

namespace App\MilitaryPatient\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\JoinType;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\MilitaryPatient\Domain\GetAllMilitaryPatientUseCase;
use App\MilitaryPatient\Domain\GetOneMilitaryPatientUseCase;
use App\MilitaryPatient\Domain\MilitaryPatient;
use App\MilitaryPatient\Infrastructure\MilitaryPatientEntity;

/**
 * @implements ApiProviderInterface<MilitaryPatient>
 */
readonly class MilitaryPatientProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllMilitaryPatientUseCase $getAllUseCase,
        private GetOneMilitaryPatientUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.patient', 'p'));
            $context = OptionUtils::addJoin($context, new JoinRule('p.type', 't'));
            $context = OptionUtils::addJoin($context, new JoinRule('p.customer', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('c.price_list', 'pl', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('c.razon_social', 'asc'));
            $options = OptionsBuilder::build($context, MilitaryPatientEntity::class, MilitaryPatient::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}