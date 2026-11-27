<?php

declare(strict_types=1);

namespace App\Sales\MessageHandler;

use App\Sales\Event\SaleConfirmed;
use App\Sales\Receivable\ReceivableOpener;
use App\Sales\Repository\SaleRecordRepository;
use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\TenantCapabilityResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Finance refactoring, phase 1 (shadow): when `finance.receivables_v2` is on, also open the new receivable of a
 * confirmed credit sale. Nothing reads it yet and a failure here never reaches the sale: it is logged and
 * `finance:audit` reports the sale that has no receivable.
 */
#[AsMessageHandler]
final readonly class OpenReceivableOnSaleConfirmed
{
    public function __construct(
        private SaleRecordRepository $sales,
        private ReceivableOpener $opener,
        private TenantCapabilityResolver $capabilities,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SaleConfirmed $event): void
    {
        if (!$this->capabilities->isEnabled(CapabilityKey::FinanceReceivablesV2)) {
            return;
        }

        try {
            $sale = $this->sales->findByAnyId($event->saleId);
            if ($sale !== null && $sale->isReceivableAccount()) {
                $this->opener->open($sale);
            }
        } catch (Throwable $e) {
            $this->logger->error('receivable.open_failed', ['sale_id' => $event->saleId, 'error' => $e->getMessage()]);
        }
    }
}
