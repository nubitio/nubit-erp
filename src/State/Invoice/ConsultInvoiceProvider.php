<?php

namespace App\State\Invoice;

use Override;
use Exception;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Http\ApiResponse;
use App\Invoice\Domain\InvoiceConsultUseCase;
use League\Flysystem\FilesystemException;

readonly class ConsultInvoiceProvider implements ProviderInterface
{
    public function __construct(
        private InvoiceConsultUseCase $invoiceConsultUseCase,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): object | array | null {
        $id = $uriVariables['id'];
        try {
            $result = $this->invoiceConsultUseCase->__invoke($id);

            if ($result->isSuccess()) {
                return
                    new ApiResponse(
                        true,
                        $result->getCdrResponse()?->getDescription() ?: ''
                    );
            }

            return
                new ApiResponse(
                    false,
                    $result->getError()?->getMessage() ?: '',
                    $result
                );
        } catch (Exception | FilesystemException $e) {
            return new ApiResponse(false, $e->getMessage(), $e);
        }
    }
}
