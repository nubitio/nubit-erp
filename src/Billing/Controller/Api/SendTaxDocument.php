<?php

declare(strict_types=1);

namespace App\Billing\Controller\Api;

use App\Billing\Invoice\Domain\InvoiceSendUseCase;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Service\CreditNoteService;
use Exception;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Http\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SendTaxDocument extends AbstractController
{
    public function __construct(
        private readonly InvoiceSendUseCase $invoiceSendUseCase,
        private readonly CreditNoteService $creditNoteService,
        private readonly TaxDocumentRepository $taxDocumentRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): Response
    {
        $id = $request->get('id');

        $taxDocument = $this->taxDocumentRepository->find($id);

        if (!$taxDocument) {
            throw new ServiceException('Documento no encontrado');
        }

        try {
            if (in_array($taxDocument->getDocumentType(), ['07', '08'], true)) {
                $result = $this->creditNoteService->sendToSunat($taxDocument);
            } else {
                $result = $this->invoiceSendUseCase->__invoke($id);
            }

            if ($result->sunatResponse->isSuccess()) {
                return new ApiResponse(true, $result->sunatResponse->getCdrResponse()?->getDescription() ?: '');
            }

            $this->logger->error('Error sending document', [
                'result' => $result->sunatResponse->getError()?->getMessage(),
            ]);

            return new ApiResponse(false, $result->sunatResponse->getError()?->getMessage() ?: '');
        } catch (Exception|FilesystemException $e) {
            $this->logger->error('Error sending document', [
                'exception' => $e,
            ]);

            return new ApiResponse(success: false, message: 'Error sending document', payload: $e->getMessage());
        }
    }
}
