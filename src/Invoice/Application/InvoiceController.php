<?php

namespace App\Invoice\Application;

use App\Base\BaseController;
use App\Core\Domain\Common\ApiResponse;
use App\Invoice\Domain\InvoiceConsultUseCase;
use App\Invoice\Domain\InvoiceEmailUseCase;
use App\Invoice\Domain\InvoiceSendUseCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends BaseController
{
    #[Route('/invoice', name: 'invoice_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('invoice/index.html.twig');
    }

    #[Route('/invoice/{id}/send', name: 'invoice_send', methods: ['POST'])]
    public function send(InvoiceSendUseCase $invoiceSendUseCase, string $id): Response
    {
        $result = $invoiceSendUseCase($id);

        if ($result->sunatResponse->isSuccess()) {
            return $this->json(new ApiResponse(true, $result->sunatResponse->getCdrResponse()?->getDescription() ?: ''));
        } else {
            return $this->json(new ApiResponse(false, $result->sunatResponse->getError()?->getMessage() ?: ''));
        }
    }

    #[Route('/invoice/{id}/consult', name: 'invoice_consult', methods: ['GET'])]
    public function consult(InvoiceConsultUseCase $invoiceConsultUseCase, string $id): Response
    {
        $result = $invoiceConsultUseCase($id);

        if ($result->isSuccess()) {
            return $this->json(new ApiResponse(true, $result->getCdrResponse()?->getDescription() ?: ''));
        } else {
            return $this->json(new ApiResponse(false, $result->getError()?->getMessage() ?: '', $result));
        }
    }

    #[Route('/invoice/{id}/email', name: 'invoice_email', methods: ['POST'])]
    public function sendEmail(InvoiceEmailUseCase $emailUseCase, string $id): Response
    {
        $emailUseCase($id);

        return $this->json(['success' => true, 'msg' => 'Email enviado']);
    }

    #[Route('/invoice/send/multiple', name: 'invoice_multiple_send', methods: ['GET'])]
    public function sendMultiple(Connection $connection): Response
    {
        $invoices = $connection->fetchAllAssociative("
        SELECT
            *
        FROM comprobante
        WHERE
            (envio_sunat REGEXP '[^0-9]' OR envio_sunat IS NULL)
            AND fecha >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ");

        $invoicesIds = array_map(function ($invoice) {
            return $invoice['movimiento_id'];
        }, $invoices);

        return $this->render('invoice/send_multiple.html.twig', [
            'invoices' => $invoices,
            'invoicesIds' => $invoicesIds,
        ]);
    }
}
