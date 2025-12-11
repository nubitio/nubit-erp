<?php

declare(strict_types=1);

namespace App\State\Invoice;

use Override;
use Exception;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Http\ApiResponse;
use App\Invoice\Domain\InvoiceSendUseCase;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;

readonly class SendInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private InvoiceSendUseCase $invoiceSendUseCase,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $id = $uriVariables['id'];

        try {
            $result = $this->invoiceSendUseCase->__invoke($id);

            if ($result->sunatResponse->isSuccess()) {
                return
                    new ApiResponse(
                        true,
                        $result->sunatResponse->getCdrResponse()
                            ?->getDescription() ?: ''
                    );
            }

            $this->logger->error('Error sending invoice', [
                'result' => $result->sunatResponse->getError()?->getMessage(),
            ]);

            return new ApiResponse(
                false,
                $result->sunatResponse->getError()?->getMessage() ?: ''
            );
        } catch (Exception | FilesystemException $e) {
            $this->logger->error('Error sending invoice', [
                'exception' => $e,
            ]);

            return new ApiResponse(
                success: false,
                message: 'Error sending invoice',
                data: $e->getMessage(),
            );
        }
    }
}
