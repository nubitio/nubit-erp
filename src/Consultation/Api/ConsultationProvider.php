<?php

namespace App\Consultation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Consultation\Domain\Consultation;
use App\Consultation\Domain\GetAllConsultationUseCase;
use App\Consultation\Domain\GetOneConsultationUseCase;
use App\Consultation\Infrastructure\ConsultationEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<Consultation>
 */
readonly class ConsultationProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllConsultationUseCase $getAllUseCase,
        private GetOneConsultationUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.patient', 'p'));
            $context = OptionUtils::addJoin($context, new JoinRule('p.customer', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.physician', 'd'));
            $context = OptionUtils::addJoin($context, new JoinRule('d.employee', 'y'));
            $context = OptionUtils::addSort($context, new SortRule('id', 'desc'));
            $options = OptionsBuilder::build($context, ConsultationEntity::class, Consultation::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}