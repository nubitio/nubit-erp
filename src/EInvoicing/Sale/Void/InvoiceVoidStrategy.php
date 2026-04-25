<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale\Void;

use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Efact\Config\EfactConstants as Constants;
use App\EInvoicing\Billing\Domain\VoidedCreateUseCase;
use App\Sale\Domain\DocType;
use App\Sale\Domain\SaleException;
use App\Repository\TaxDocumentRepository;
use League\Flysystem\FilesystemException;

final readonly class InvoiceVoidStrategy implements VoidStrategyInterface
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private VoidedCreateUseCase $voidedCreateUseCase,
        private FileManager $fileManager,
    ) {
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocType::FACTURA->value;
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     */
    public function void(string $saleId, string $reason): SunatVoidResponse
    {
        $taxDocument = $this->taxDocumentRepository->findOneBy([
            'saleRecord' => $saleId,
            'documentType' => DocType::FACTURA->value,
        ]);

        if ($taxDocument === null) {
            throw new ServiceException('Comprobante no encontrado');
        }

        if (($taxDocument->getSunatStatus() ?? null) !== '0') {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke((string) $taxDocument->getId(), $reason);
        $ticket = $response->sunatResponse->getTicket() ?? '';
        $success = $response->sunatResponse->isSuccess();
        $message = $success ? 'Solicitud de anulación aceptada' : ($response->sunatResponse->getError()?->getMessage());

        if ($success) {
            $this->fileManager->write(
                Constants::INVOICE_DIR . '/' . $response->name . '/' . $response->name . '.xml',
                $response->xml
            );
        }

        return new SunatVoidResponse(
            rawResponse: $response,
            name: $response->name,
            ticket: (string)$ticket,
            success: $success,
            message: $message,
        );
    }
}
