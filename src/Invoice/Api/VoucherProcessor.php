<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Invoice\Domain\Voucher;
use App\Sale\Domain\SaleInvoiceUseCase;

readonly class VoucherProcessor implements ProcessorInterface
{
    public function __construct(
        public SaleInvoiceUseCase $saleInvoiceUseCase,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Voucher
    {
        if (!$data->sale_id) {
            return null;
        }

        return $this->saleInvoiceUseCase->__invoke($data->sale_id);
    }
}
