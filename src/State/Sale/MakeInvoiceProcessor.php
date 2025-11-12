<?php

namespace App\State\Sale;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Common\ApiResponse;
use App\Sale\Domain\SaleInvoiceUseCase;

readonly class MakeInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private SaleInvoiceUseCase $saleInvoiceUseCase,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $this->saleInvoiceUseCase->__invoke($uriVariables['id']);

        return new ApiResponse(
            success: true,
            message: 'Comprobante generado correctamente'
        );
    }
}
