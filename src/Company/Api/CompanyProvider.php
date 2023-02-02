<?php

namespace App\Company\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Company\Domain\GetAllCompanyUseCase;
use App\Company\Domain\GetOneCompanyUseCase;
use App\Company\Infrastructure\CompanyEntity;

readonly class CompanyProvider implements ProviderInterface
{
    public function __construct(
        private GetAllCompanyUseCase $getAllUseCase,
        private GetOneCompanyUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('razon_social', 'asc'));
            $options = OptionsBuilder::build($context, CompanyEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['empresa_id']);
    }
}