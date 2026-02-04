<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\Invoice\Domain\InvoiceSendUseCase;
use Exception;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SendInvoice extends AbstractController
{
    public function __construct(
        private readonly InvoiceSendUseCase $invoiceSendUseCase,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->get('id');

        try {
            $result = $this->invoiceSendUseCase->__invoke($id);

            if ($result->sunatResponse->isSuccess()) {
                return
                    new ApiResponse(
                        true,
                        $result->sunatResponse->getCdrResponse()
                            ?->getDescription() ?: ''
                    );
            }

            $this->logger->error('Error sending invoice', [
                'result' => $result->sunatResponse->getError()?->getMessage(),
            ]);

            return new ApiResponse(
                false,
                $result->sunatResponse->getError()?->getMessage() ?: ''
            );
        } catch (Exception | FilesystemException $e) {
            $this->logger->error('Error sending invoice', [
                'exception' => $e,
            ]);

            return new ApiResponse(
                success: false,
                message: 'Error sending invoice',
                data: $e->getMessage(),
            );
        }
    }
}
