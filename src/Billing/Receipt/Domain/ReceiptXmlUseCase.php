<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Domain;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\FileResponse;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\CompanyContext;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Filesystem\FileManager;

readonly class ReceiptXmlUseCase
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private CompanyContext $companyContext,
        private FileManager $fileManager,
    ) {}

    /**
     * @throws FilesystemException
     */
    public function __invoke(ReceiptRequest $receiptRequest): FileResponse
    {
        return match ($receiptRequest->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->getXmlForInvoice($receiptRequest->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->getXmlForNote($receiptRequest->id),
        };
    }

    /**
     * @throws FilesystemException
     */
    public function getXmlForInvoice(string $id): FileResponse
    {
        $invoice = $this->taxDocumentRepository->find($id);
        $companyId = $this->companyContext->getId();

        $docName = $invoice->getFullNumber();
        $docFullName = $companyId . '-' . $invoice->getDocumentType() . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read(Constants::INVOICE_DIR . '/' . $docFullName . '/' . $docFullName . '.xml'),
            $docFullName . '.xml',
        );
    }

    /**
     * @throws FilesystemException
     */
    public function getXmlForNote(string $id): FileResponse
    {
        $note = $this->taxDocumentRepository->find($id);
        $companyId = $this->companyContext->getId();

        $docName = $note->getFullNumber();
        $docFullName = $companyId . '-' . $note->getDocumentType() . '-' . $docName;

        return new FileResponse(
            $this->fileManager->read(Constants::INVOICE_DIR . '/' . $docFullName . '/' . $docFullName . '.xml'),
            $docFullName . '.xml',
        );
    }
}
