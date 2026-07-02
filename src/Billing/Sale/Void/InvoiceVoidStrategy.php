<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\VoidedCreateUseCase;
use App\Efact\Config\EfactConstants as Constants;
use App\Sales\Domain\DocType;
use App\Sales\Domain\SaleException;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Filesystem\FileManager;

final readonly class InvoiceVoidStrategy implements VoidStrategyInterface
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private VoidedCreateUseCase $voidedCreateUseCase,
        private FileManager $fileManager,
    ) {}

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
        $taxDocument = $this->taxDocumentRepository->findLatestAcceptedBySaleAndType($saleId, DocType::FACTURA->value);

        if ($taxDocument === null) {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke((string) $taxDocument->getId(), $reason);
        $ticket = $response->sunatResponse->getTicket() ?? '';
        $success = $response->sunatResponse->isSuccess();
        $message = $success ? 'Solicitud de anulación aceptada' : $response->sunatResponse->getError()?->getMessage();

        if ($success) {
            $this->fileManager->write(
                Constants::INVOICE_DIR . '/' . $response->name . '/' . $response->name . '.xml',
                $response->xml,
            );
        }

        return new SunatVoidResponse(
            rawResponse: $response,
            name: $response->name,
            ticket: (string) $ticket,
            success: $success,
            message: $message,
        );
    }
}
