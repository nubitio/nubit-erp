<?php

declare(strict_types=1);

namespace App\Billing\MessageHandler;

use App\Billing\Message\PrewarmVoucherPdfMessage;
use App\Billing\Receipt\Service\VoucherPdfRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PrewarmVoucherPdfMessageHandler
{
    public function __construct(
        private VoucherPdfRenderer $renderer,
        private LoggerInterface $billingLogger,
    ) {}

    public function __invoke(PrewarmVoucherPdfMessage $message): void
    {
        try {
            // render() writes the PDF cache as a side effect.
            $this->renderer->render($message->taxDocumentId);
        } catch (\Throwable $e) {
            // Best-effort: the PDF is still rendered on demand if this fails.
            $this->billingLogger->warning('voucher_pdf.prewarm_failed', [
                'tax_document_id' => $message->taxDocumentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
