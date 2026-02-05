<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\EInvoicing\Invoice\Domain\InvoiceConsultUseCase;
use Exception;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class ConsultInvoice extends AbstractController
{
    public function __construct(
        private readonly InvoiceConsultUseCase $invoiceConsultUseCase,
    ) {
    }

    public function __invoke(Request $request)
    {
        $id = $request->get('id');
        try {
            $result = $this->invoiceConsultUseCase->__invoke($id);

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
