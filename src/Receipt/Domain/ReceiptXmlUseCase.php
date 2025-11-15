<?php

namespace App\Receipt\Domain;

use App\Billing\Domain\FileResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use League\Flysystem\FilesystemException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class ReceiptXmlUseCase
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
     * @throws FilesystemException|\Doctrine\DBAL\Exception
     * @throws ExceptionInterface
     */
    public function __invoke(ReceiptRequest $receiptRequest): FileResponse
    {
        return match ($receiptRequest->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->getXmlForInvoice($receiptRequest->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->getXmlForNote($receiptRequest->id),
        };
    }

    /**
     * @throws FilesystemException|\Doctrine\DBAL\Exception
     * @throws ExceptionInterface
     */
    public function getXmlForInvoice(string $id): FileResponse
    {
        $voucher = $this->invoiceRepository->getVoucher($id);
        $companyId = $this->settings->get(Constants::COMPANY_ID);

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $docFullName = $companyId . '-' . $voucher->voucher_type . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read($companyId . '/' . $docFullName . '.xml'),
            $docFullName . '.xml'
        );
    }

    /**
     * @throws ServiceException
     * @throws FilesystemException|\Doctrine\DBAL\Exception
     */
    public function getXmlForNote(string $id): FileResponse
    {
        $note = $this->creditNoteRepository->find($id);
        $companyId = $this->settings->get(Constants::COMPANY_ID);

        $docName = $note->getSerie() . '-' . $note->getcorrelativo();
        $docFullName = $companyId . '-' . $note->getTipoId() . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read($companyId . '/' . $docFullName . '.xml'),
            $docFullName . '.xml'
        );
    }
}
