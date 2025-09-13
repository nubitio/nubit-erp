<?php

namespace App\Sale\Domain;

use App\Billing\Domain\SummaryCreateUseCase;
use App\Billing\Domain\VoidedCreateUseCase;
use App\Billing\Model\Response\SummaryResponse;
use App\Core\Domain\Common\ApiResponse;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use App\Entity\Sale;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;

readonly class SaleVoidUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceRepositoryInterface $invoiceRepository,
        private VoidedCreateUseCase $voidedCreateUseCase,
        private SummaryCreateUseCase $summaryCreateUseCase,
        private FileManager $fileManager,
        private SettingsService $settings,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws Exception
     */
    public function __invoke(
        string $saleId,
        string $reason,
        int $userId,
    ): ApiResponse {
        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $sale = $this->entityManager->find(Sale::class, $saleId);

        if (2 === $sale->getEstado()) {
            throw SaleException::alreadyVoided();
        }

        if (
            $sale->getFecha()?->format('Y-m-d') < date(
                'Y-m-d',
                strtotime('-7 days')
            )
        ) {
            throw SaleException::tooOld();
        }

        $response = null;

        if ($voucher->voucher_type === DocType::FACTURA->value) {
            $response = $this->voidInvoice($saleId, $reason);
        } elseif ($voucher->voucher_type === DocType::BOLETA->value) {
            $response = $this->voidReceipt($saleId);
        }

        $sale->setGlosa($reason);
        $sale->setEstado(2);
        $sale->setUpdatedBy($userId);
        $sale->setDateUpdated(new \DateTime());

        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        if ($response instanceof SummaryResponse) {
            $sunatResponse = $response->sunatResponse;
            $success = $sunatResponse->isSuccess();
            $description = $success ? 'Anulado correctamente'
                : $sunatResponse->getError()?->getMessage();

            return new ApiResponse(
                $success ?? false,
                $description ?? 'Error al anular'
            );
        }

        return new ApiResponse(
            true,
            'Anulado correctamente'
        );
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws Exception
     */
    private function voidInvoice(
        string $saleId,
        string $reason,
    ): SummaryResponse {
        $invoice = $this->invoiceRepository->getOneBySaleId($saleId);

        if ('0' !== $invoice->envio_sunat) {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke($saleId, $reason);
        $invoice->correlativo_baja = explode('-', $response->name)[3];
        $invoice->updated_at = new \DateTime();
        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                $this->settings->get(Constants::COMPANY_ID) . '/' . $response->name
                . '.xml',
                $response->xml
            );
            $invoice->ticket_baja = $response->sunatResponse->getTicket();
            $invoice->voided = 2;
        } else {
            $invoice->ticket_baja = $response->sunatResponse->getTicket() ??
                $invoice->ticket_baja;
            $invoice->voided = 3;
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $response;
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws \Exception
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
            $this->fileManager->write(
                $this->settings->get(Constants::COMPANY_ID) . '/' . $response->name
                . '.xml',
                $response->xml
            );
            $invoice->ticket_baja = $response->sunatResponse->getTicket();
            $invoice->voided = 2;
        } else {
            $invoice->ticket_baja = $response->sunatResponse->getTicket() ??
                $invoice->ticket_baja;
            $invoice->voided = 3;
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $response;
    }
}
