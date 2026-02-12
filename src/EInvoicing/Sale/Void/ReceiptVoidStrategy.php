<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale\Void;

use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Util\Constants;
use App\EInvoicing\Billing\Domain\SummaryCreateUseCase;
use App\Sale\Domain\DocType;
use App\Sale\Domain\SaleException;
use App\Repository\TaxDocumentRepository;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;

final readonly class ReceiptVoidStrategy implements VoidStrategyInterface
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private SummaryCreateUseCase $summaryCreateUseCase,
        private FileManager $fileManager,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocType::BOLETA->value;
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     */
    public function void(string $saleId, string $reason): SunatVoidResponse
    {
        $taxDocument = $this->taxDocumentRepository->findOneBy([
            'sale' => $saleId,
            'documentType' => DocType::BOLETA->value,
        ]);

        if ($taxDocument === null) {
            throw new ServiceException('Comprobante no encontrado');
        }

        if (($taxDocument->getSunatStatus() ?? null) !== '0') {
            throw SaleException::notIssued();
        }

        $response = $this->summaryCreateUseCase->__invoke($saleId);
        $ticket = $response->sunatResponse->getTicket() ?? '';

        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                Constants::INVOICE_DIR . '/' . $response->name . '/' . $response->name . '.xml',
                $response->xml
            );
        } else {
            $this->logger->error('Error al anular el comprobante', [
                'response' => $response,
                'sale_id' => $saleId,
                'message' => $response->sunatResponse->getError()?->getMessage() ?? 'Error desconocido',
            ]);
            throw new ServiceException(
                'Error al anular el comprobante' . $response->sunatResponse->getError()?->getMessage()
            );
        }

        return new SunatVoidResponse(
            rawResponse: $response,
            name: $response->name,
            ticket: $ticket,
            success: true,
            message: 'Solicitud de anulación aceptada',
        );
    }
}
