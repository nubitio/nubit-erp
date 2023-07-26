<?php

namespace App\RawDocument\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\RawDocument\Domain\GetAllRawDocumentUseCase;
use App\RawDocument\Domain\GetOneRawDocumentUseCase;
use App\RawDocument\Domain\RawDocument;
use App\RawDocument\Infrastructure\RawDocumentEntity;

/**
 * @implements ApiProviderInterface<RawDocument>
 */
readonly class RawDocumentProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllRawDocumentUseCase $getAllUseCase,
        private GetOneRawDocumentUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('created_at', 'desc'));
            $options = OptionsBuilder::build($context, RawDocumentEntity::class, RawDocument::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
