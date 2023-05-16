<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Invoice\Domain\Voucher;
use App\Sale\Domain\SaleInvoiceUseCase;

/**
 * @implements ApiProcessorInterface<Voucher>
 */
readonly class VoucherProcessor implements ApiProcessorInterface
{
    public function __construct(
        public SaleInvoiceUseCase $saleInvoiceUseCase
    ) {
    }

    /** {@inheritDoc} */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Voucher
    {
        if (!$data->sale_id) {
            return null;
        }

        return $this->saleInvoiceUseCase->__invoke($data->sale_id);
    }
}
