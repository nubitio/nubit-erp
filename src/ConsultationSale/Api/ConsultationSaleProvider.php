<?php

namespace App\ConsultationSale\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\ConsultationSale\Domain\ConsultationSale;
use App\ConsultationSale\Domain\GetAllConsultationSaleUseCase;
use App\ConsultationSale\Domain\GetOneConsultationSaleUseCase;
use App\ConsultationSale\Infrastructure\ConsultationSaleEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<ConsultationSale>
 */
readonly class ConsultationSaleProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllConsultationSaleUseCase $getAllUseCase,
        private GetOneConsultationSaleUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.consultation', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.sale', 's'));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, ConsultationSaleEntity::class, ConsultationSale::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
