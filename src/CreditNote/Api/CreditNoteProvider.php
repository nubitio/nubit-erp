<?php

namespace App\CreditNote\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\GetAllCreditNoteUseCase;
use App\CreditNote\Domain\GetOneCreditNoteUseCase;
use App\CreditNote\Infrastructure\CreditNoteEntity;

/**
 * @implements ApiProviderInterface<CreditNote>
 */
readonly class CreditNoteProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllCreditNoteUseCase $getAllUseCase,
        private GetOneCreditNoteUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.sale', 's'));
            $context = OptionUtils::addSort($context, new SortRule('created_at', 'desc'));
            $options = OptionsBuilder::build($context, CreditNoteEntity::class, CreditNote::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['nota_id']));
    }
}
