<?php

namespace App\Company\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Company\Domain\Company;
use App\Company\Domain\GetAllCompanyUseCase;
use App\Company\Domain\GetOneCompanyUseCase;
use App\Company\Infrastructure\CompanyEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<Company>
 */
readonly class CompanyProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllCompanyUseCase $getAllUseCase,
        private GetOneCompanyUseCase $getOneUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.logo', 'm', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('razon_social', 'asc'));
            $options = OptionsBuilder::build($context, CompanyEntity::class, Company::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['empresa_id']));
    }
}
