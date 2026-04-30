<?php

declare(strict_types=1);

namespace App\EInvoicing\Controller\Api;

use Nubit\Platform\Exception\ServiceException;
use App\Core\Http\ApiResponse;
use App\EInvoicing\Invoice\Domain\InvoiceConsultUseCase;
use App\EInvoicing\Service\CreditNoteService;
use App\Billing\Repository\TaxDocumentRepository;
use Exception;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConsultInvoice extends AbstractController
{
    public function __construct(
        private readonly InvoiceConsultUseCase $invoiceConsultUseCase,
        private readonly CreditNoteService $creditNoteService,
        private readonly TaxDocumentRepository $taxDocumentRepository,
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
            if (in_array($taxDocument->getDocumentType(), ['07', '08'], true)) {
                $result = $this->creditNoteService->consultCdr($taxDocument);
            } else {
                $result = $this->invoiceConsultUseCase->__invoke($id);
            }

            if ($result->isSuccess()) {
                return
                    new ApiResponse(
                        true,
                        $result->getCdrResponse()?->getDescription() ?: ''
                    );
            }

            return
                new ApiResponse(
                    false,
                    $result->getError()?->getMessage() ?: '',
                    $result
                );
        } catch (Exception | FilesystemException $e) {
            return new ApiResponse(false, $e->getMessage(), $e);
        }
    }
}
