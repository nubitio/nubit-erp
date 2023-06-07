<?php

namespace App\Billing\Domain;

use App\Invoice\Domain\InvoiceCreatedEvent;
use App\Invoice\Domain\InvoiceSendUseCase;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class InvoiceCreatedListener
{
    public function __construct(
        private InvoiceSendUseCase $invoiceSendUseCase,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    public function __invoke(InvoiceCreatedEvent $event): void
    {
        $saleId = $event->invoice->movimiento_id;

        if (null === $saleId) {
            return;
        }

        $this->invoiceSendUseCase->__invoke($saleId);
    }
}
