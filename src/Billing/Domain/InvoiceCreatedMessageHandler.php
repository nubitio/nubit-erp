<?php

namespace App\Billing\Domain;

use App\Core\Domain\Exception\ServiceException;
use App\Invoice\Domain\InvoiceCreatedMessage;
use App\Invoice\Domain\InvoiceSendUseCase;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class InvoiceCreatedMessageHandler
{
    public function __construct(
        private InvoiceSendUseCase $invoiceSendUseCase,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws ServiceException
     */
    public function __invoke(InvoiceCreatedMessage $invoiceCreatedMessage): void
    {
        $saleId = $invoiceCreatedMessage->invoice->movimiento_id;

        if (null === $saleId) {
            return;
        }

        $this->invoiceSendUseCase->__invoke($saleId);
    }
}
