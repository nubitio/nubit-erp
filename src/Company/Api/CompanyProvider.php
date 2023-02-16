<?php

namespace App\Company\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Company\Domain\Company;
use App\Company\Domain\GetAllCompanyUseCase;
use App\Company\Domain\GetOneCompanyUseCase;
use App\Company\Infrastructure\CompanyEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

readonly class CompanyProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllCompanyUseCase $getAllUseCase,
        private GetOneCompanyUseCase $getOneUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('razon_social', 'asc'));
            $options = OptionsBuilder::build($context, CompanyEntity::class, Company::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['empresa_id']);
    }
}