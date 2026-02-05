<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\EInvoicing\Billing\Domain\BillSendUseCase;
use App\Message\SendInvoiceMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendInvoiceMessageHandler
{
    public function __construct(
        private readonly BillSendUseCase $billSendUseCase
    ) {
    }

    public function __invoke(SendInvoiceMessage $message): void
    {
        $this->billSendUseCase->__invoke($message->saleId);
    }
}
