<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\SummaryCreateUseCase;
use App\Efact\Config\EfactConstants as Constants;
use App\Sales\Domain\DocType;
use App\Sales\Domain\SaleException;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;
use Psr\Log\LoggerInterface;

final readonly class ReceiptVoidStrategy implements VoidStrategyInterface
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private SummaryCreateUseCase $summaryCreateUseCase,
        private FileManager $fileManager,
        private LoggerInterface $logger,
    ) {}

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
        $this->logger->info('Solicitando anulación de boleta', [
            'sale_id' => $saleId,
            'reason' => $reason,
        ]);

        $taxDocument = $this->taxDocumentRepository->findLatestAcceptedBySaleAndType($saleId, DocType::BOLETA->value);

        if ($taxDocument === null) {
            throw SaleException::notIssued();
        }

        $response = $this->summaryCreateUseCase->__invoke((string) $taxDocument->getId());
        $ticket = $response->sunatResponse->getTicket() ?? '';

        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                Constants::INVOICE_DIR . '/' . $response->name . '/' . $response->name . '.xml',
                $response->xml,
            );
        } else {
            $this->logger->error('Error al anular el comprobante', [
                'response' => $response,
                'sale_id' => $saleId,
                'message' => $response->sunatResponse->getError()?->getMessage() ?? 'Error desconocido',
            ]);
            throw new ServiceException(
                'Error al anular el comprobante' . $response->sunatResponse->getError()?->getMessage(),
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
