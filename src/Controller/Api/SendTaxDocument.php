<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Exception\ServiceException;
use App\Core\Http\ApiResponse;
use App\EInvoicing\Invoice\Domain\InvoiceSendUseCase;
use App\Repository\TaxDocumentRepository;
use App\EInvoicing\Service\CreditNoteService;
use Exception;
use League\Flysystem\FilesystemException;
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
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->get('id');

        $taxDocument = $this->taxDocumentRepository->find($id);

        if (!$taxDocument) {
            throw new ServiceException('Documento no encontrado');
        }

        try {
            if ($taxDocument->getDocumentType() === '07') {
                $result = $this->creditNoteService->sendToSunat($taxDocument);
            } else {
                $result = $this->invoiceSendUseCase->__invoke($id);
            }

            if ($result->sunatResponse->isSuccess()) {
                return
                    new ApiResponse(
                        true,
                        $result->sunatResponse->getCdrResponse()
                            ?->getDescription() ?: ''
                    );
            }

            $this->logger->error('Error sending document', [
                'result' => $result->sunatResponse->getError()?->getMessage(),
            ]);

            return new ApiResponse(
                false,
                $result->sunatResponse->getError()?->getMessage() ?: ''
            );
        } catch (Exception | FilesystemException $e) {
            $this->logger->error('Error sending document', [
                'exception' => $e,
            ]);

            return new ApiResponse(
                success: false,
                message: 'Error sending document',
                data: $e->getMessage(),
            );
        }
    }
}
