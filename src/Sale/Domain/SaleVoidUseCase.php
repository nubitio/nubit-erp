<?php

namespace App\Sale\Domain;

use App\Billing\Domain\SummaryCreateUseCase;
use App\Billing\Domain\VoidedCreateUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;

readonly class SaleVoidUseCase
{
    public function __construct(
        private SaleRepositoryInterface $saleRepository,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private VoidedCreateUseCase $voidedCreateUseCase,
        private SummaryCreateUseCase $summaryCreateUseCase,
        private FileManager $fileManager,
        private TenantAwareSettingsService $settings
    ) {
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    public function __invoke(string $saleId, string $reason, int $userId): void
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);
        $sale = $this->saleRepository->getOne($saleId);

        if (2 === $sale->estado) {
            throw SaleException::alreadyVoided();
        }

        if ($sale->fecha?->format('Y-m-d') < date('Y-m-d', strtotime('-7 days'))) {
            throw SaleException::tooOld();
        }

        if ($voucher->voucher_type === DocType::FACTURA->value) {
            $this->voidInvoice($saleId, $reason);
        } elseif ($voucher->voucher_type === DocType::BOLETA->value) {
            $this->voidReceipt($saleId);
        }

        $sale->glosa = $reason;
        $sale->estado = 2;
        $sale->updated_by = $userId;
        $sale->date_updated = new \DateTime();

        $this->saleRepository->update($sale);
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    private function voidInvoice(string $saleId, string $reason): void
    {
        $invoice = $this->invoiceRepository->getOne($saleId);

        if ('0' !== $invoice->envio_sunat) {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke($saleId, $reason);
        if ($response->sunatResponse->success) {
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/'.$response->name.'.xml', $response->xml);
            $invoice->ticket_baja = $response->sunatResponse->ticket;
            $invoice->correlativo_baja = explode('-', $response->name)[3];
            $invoice->updated_at = new \DateTime();
            $this->invoiceRepository->update($invoice);
        } else {
            throw new \Exception($response->sunatResponse->error->message);
        }
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    private function voidReceipt(string $saleId): void
    {
        $invoice = $this->invoiceRepository->getOne($saleId);

        if ('0' !== $invoice->envio_sunat) {
            throw SaleException::notIssued();
        }

        $response = $this->summaryCreateUseCase->__invoke($saleId);
        if ($response->sunatResponse->success) {
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/'.$response->name.'.xml', $response->xml);
            $invoice->ticket_baja = $response->sunatResponse->ticket;
            $invoice->correlativo_baja = explode('-', $response->name)[3];
            $invoice->updated_at = new \DateTime();
            $this->invoiceRepository->update($invoice);
        } else {
            throw new \Exception($response->sunatResponse->error->message);
        }
    }
}
