<?php

declare(strict_types=1);

namespace App\Billing\MessageHandler;

use App\Billing\Message\SendInvoiceMessage;
use App\Billing\Sunat\Domain\BillSendUseCase;
use Brick\Math\Exception\MathException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendInvoiceMessageHandler
{
    public function __construct(
        private BillSendUseCase $billSendUseCase,
        private LoggerInterface $billingLogger,
    ) {}

    /**
     * @throws \DateMalformedStringException
     * @throws MathException
     */
    public function __invoke(SendInvoiceMessage $message): void
    {
        $tenantId = $message->getTenantId() ?: 'unknown';

        $this->billingLogger->info('send_invoice.start', [
            'tenant_id' => $tenantId,
            'tax_document_id' => $message->taxDocumentId,
        ]);

        try {
            $this->billSendUseCase->__invoke($message->taxDocumentId);
        } catch (\Throwable $e) {
            $this->billingLogger->error('send_invoice.failed', [
                'tenant_id' => $tenantId,
                'tax_document_id' => $message->taxDocumentId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }

        $this->billingLogger->info('send_invoice.done', [
            'tenant_id' => $tenantId,
            'tax_document_id' => $message->taxDocumentId,
        ]);
    }
}
