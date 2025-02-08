<?php

namespace App\State\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Domain\Common\ApiResponse;
use App\Invoice\Domain\ValidateInvoiceUseCase;
use Psr\Cache\InvalidArgumentException;

readonly class ValidateInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private ValidateInvoiceUseCase $validateInvoiceUseCase,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $id = $uriVariables['id'];
        try {
            $result = $this->validateInvoiceUseCase->__invoke($id);

            if ($result->getSuccess()) {
                return new ApiResponse(
                    true,
                    $result->getMessage() ?? '',
                    $result
                );
            } else {
                return new ApiResponse(
                    false,
                    $result->getMessage() ?? '',
                    $result
                );
            }
        } catch (\Exception|InvalidArgumentException $e) {
            return new ApiResponse(false, $e->getMessage(), $e);
        }
    }
}
