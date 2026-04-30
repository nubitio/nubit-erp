<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

use Psr\Log\LoggerInterface;
use Throwable;

readonly class InvoiceSendRecoveryService
{
    public function __construct(
        private InvoiceConsultUseCase $invoiceConsultUseCase,
        private LoggerInterface $logger,
    ) {
    }

    public function tryRecoverByConsult(string $taxDocumentId, ?string $tenantName): bool
    {
        try {
            $consultResponse = $this->invoiceConsultUseCase->__invoke($taxDocumentId);
            if (!$consultResponse->isSuccess()) {
                return false;
            }

            return $consultResponse->getCdrResponse()?->isAccepted() ?? false;
        } catch (Throwable $exception) {
            $this->logger->warning('SUNAT consult recovery failed after send exception', [
                'tax_document_id' => $taxDocumentId,
                'tenant' => $tenantName,
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
