<?php

namespace App\Sale\Domain;

use App\Billing\Domain\SummaryCreateUseCase;
use App\Billing\Domain\VoidedCreateUseCase;
use App\Billing\Model\Response\SummaryResponse;
use App\Core\Domain\Common\ApiResponse;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
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
        private SettingsService $settings,
    ) {
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    public function __invoke(string $saleId, string $reason, int $userId): ApiResponse
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);
        $sale = $this->saleRepository->getOne($saleId);

        if (2 === $sale->estado) {
            throw SaleException::alreadyVoided();
        }

        if ($sale->fecha?->format('Y-m-d') < date('Y-m-d', strtotime('-7 days'))) {
            throw SaleException::tooOld();
        }

        $response = null;

        if ($voucher->voucher_type === DocType::FACTURA->value) {
            $response = $this->voidInvoice($saleId, $reason);
        } elseif ($voucher->voucher_type === DocType::BOLETA->value) {
            $response = $this->voidReceipt($saleId);
        }

        $sale->glosa = $reason;
        $sale->estado = 2;
        $sale->updated_by = $userId;
        $sale->date_updated = new \DateTime();

        $this->saleRepository->update($sale);

        if ($response) {
            $sunatResponse = $response->sunatResponse;
            $success = $sunatResponse->isSuccess();
            $description = $success ? 'Anulado correctamente' : $sunatResponse->getError()?->getMessage();

            return new ApiResponse($success ?? false, $description ?? 'Error al anular');
        }

        return new ApiResponse(
            true,
            'Anulado correctamente'
        );
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    private function voidInvoice(string $saleId, string $reason): SummaryResponse
    {
        $invoice = $this->invoiceRepository->getOneBySaleId($saleId);

        if ('0' !== $invoice->envio_sunat) {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke($saleId, $reason);
        $invoice->correlativo_baja = explode('-', $response->name)[3];
        $invoice->updated_at = new \DateTime();
        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/'.$response->name.'.xml', $response->xml);
            $invoice->ticket_baja = $response->sunatResponse->getTicket();
            $invoice->voided = 2;
        } else {
            $invoice->ticket_baja = $response->sunatResponse->getTicket() ?? $invoice->ticket_baja;
            $invoice->voided = 3;
        }
        $this->invoiceRepository->update($invoice);

        return $response;
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    private function voidReceipt(string $saleId): SummaryResponse
    {
        $invoice = $this->invoiceRepository->getOneBySaleId($saleId);

        if ('0' !== $invoice->envio_sunat) {
            throw SaleException::notIssued();
        }

        $response = $this->summaryCreateUseCase->__invoke($saleId);
        $invoice->correlativo_baja = explode('-', $response->name)[3];
        $invoice->updated_at = new \DateTime();
        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/'.$response->name.'.xml', $response->xml);
            $invoice->ticket_baja = $response->sunatResponse->getTicket();
            $invoice->voided = 2;
        } else {
            $invoice->ticket_baja = $response->sunatResponse->getTicket() ?? $invoice->ticket_baja;
            $invoice->voided = 3;
        }
        $this->invoiceRepository->update($invoice);

        return $response;
    }
}
