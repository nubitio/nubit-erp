<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Invoice\Domain\GetAllInvoiceUseCase;
use App\Invoice\Domain\GetOneInvoiceBySaleIdUseCase;
use App\Invoice\Domain\Invoice;
use App\Invoice\Infrastructure\InvoiceEntity;

/**
 * @implements ApiProviderInterface<Invoice>
 */
readonly class InvoiceProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllInvoiceUseCase $getAllUseCase,
        private GetOneInvoiceBySaleIdUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.cliente', 'c'));
            $context = OptionUtils::addSort($context, new SortRule('created_at', 'desc'));
            $options = OptionsBuilder::build($context, InvoiceEntity::class, Invoice::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['comprobante_id']));
    }
}
