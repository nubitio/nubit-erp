<?php

declare(strict_types=1);

namespace App\EInvoicing\Receipt\Domain;

use App\EInvoicing\Billing\Domain\FileResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Util\Constants;
use App\Core\Service\CompanyContext;
use App\Repository\TaxDocumentRepository;
use League\Flysystem\FilesystemException;

readonly class ReceiptCdrUseCase
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private CompanyContext $companyContext,
        private FileManager $fileManager,
    ) {
    }

    /**
     * @throws FilesystemException
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
     */
    public function getCdrForInvoice(string $id): FileResponse
    {
        $invoice = $this->taxDocumentRepository->find($id);
        $companyId = $this->companyContext->getId();

        $docName = $invoice->getFullNumber();
        $docFullName = $companyId . '-' . $invoice->getDocumentType() . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read(Constants::INVOICE_DIR . '/' . $docFullName . '/R-' . $docFullName . '.zip'),
            'R-' . $docFullName . '.zip'
        );
    }

    /**
     * @throws FilesystemException
     * @throws ServiceException
     */
    public function getCdrForNote(string $id): FileResponse
    {
        $note = $this->taxDocumentRepository->find($id);
        $companyId = $this->companyContext->getId();

        $docName = $note->getFullNumber();
        $docFullName = $companyId . '-' . $note->getDocumentType() . '-' . $docName;
        $cdrPath = Constants::INVOICE_DIR . '/' . $docFullName . '/R-' . $docFullName . '.zip';

        if (!$this->fileManager->exists($cdrPath)) {
            throw new ServiceException(
                'CDR no disponible. Use Consulta CDR para sincronizar el estado con SUNAT.'
            );
        }

        return new FileResponse(
            $this->fileManager->read($cdrPath),
            'R-' . $docFullName . '.zip'
        );
    }
}
