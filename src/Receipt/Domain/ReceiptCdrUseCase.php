<?php

namespace App\Receipt\Domain;

use App\Billing\Domain\FileResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class ReceiptCdrUseCase
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private CreditNoteRepository $creditNoteRepository,
        private SettingsService $settings,
        private FileManager $fileManager,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws FilesystemException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(ReceiptRequest $receiptRequest): FileResponse
    {
        return match ($receiptRequest->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->getCdrForInvoice($receiptRequest->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->getCdrForNote($receiptRequest->id),
        };
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function getCdrForInvoice(string $id): FileResponse
    {
        $voucher = $this->invoiceRepository->getVoucher($id);
        $companyId = $this->settings->get(Constants::COMPANY_ID);

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $docFullName = $companyId . '-' . $voucher->voucher_type . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read($companyId . '/R-' . $docFullName . '.zip'),
            'R-' . $docFullName . '.zip'
        );
    }

    /**
     * @throws ServiceException
     * @throws FilesystemException
     * @throws Exception
     */
    public function getCdrForNote(string $id): FileResponse
    {
        $voucher = $this->creditNoteRepository->find($id);
        $companyId = $this->settings->get(Constants::COMPANY_ID);

        $docName = $voucher->getSerie() . '-' . $voucher->getCorrelativo();
        $docFullName = $companyId . '-' . $voucher->getTipoId() . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read($companyId . '/R-' . $docFullName . '.zip'),
            'R-' . $docFullName . '.zip'
        );
    }
}
