<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\EInvoicing\Invoice\Domain\InvoiceEmailUseCase;
use Exception;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendInvoiceEmail extends AbstractController
{
    public function __construct(
        private readonly InvoiceEmailUseCase $emailUseCase,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $id = $request->get('id');
            $this->emailUseCase->__invoke($id);

            return new ApiResponse(true, 'Email enviado');
        } catch (Exception | FilesystemException | TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return new ApiResponse(false, 'Error al enviar el email', $e);
        }
    }
}
