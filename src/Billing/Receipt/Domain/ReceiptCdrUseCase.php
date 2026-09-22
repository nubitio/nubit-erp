<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Domain;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\FileResponse;
use App\Efact\Config\EfactConstants as Constants;
use App\Organization\Service\CompanyContext;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;

readonly class ReceiptCdrUseCase
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
            'R-' . $docFullName . '.zip',
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
            throw new ServiceException('CDR no disponible. Use Consulta CDR para sincronizar el estado con SUNAT.');
        }

        return new FileResponse($this->fileManager->read($cdrPath), 'R-' . $docFullName . '.zip');
    }
}
