<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Invoice\Domain\InvoiceRepositoryInterface;

readonly class VoucherProvider implements ProviderInterface
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        return $this->invoiceRepository->getVoucher(strval($uriVariables['sale_id']));
    }
}
