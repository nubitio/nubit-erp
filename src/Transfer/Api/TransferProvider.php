<?php

namespace App\Transfer\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Transfer\Domain\GetAllTransferUseCase;
use App\Transfer\Domain\GetOneTransferUseCase;
use App\Transfer\Domain\Transfer;
use App\Transfer\Infrastructure\TransferEntity;

/**
 * @implements ApiProviderInterface<Transfer>
 */
readonly class TransferProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllTransferUseCase $getAllUseCase,
        private GetOneTransferUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('transfer_id', 'desc'));
            $options = OptionsBuilder::build($context, TransferEntity::class, Transfer::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['transfer_id']));
    }
}
