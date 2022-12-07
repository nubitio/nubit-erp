<?php

namespace App\Invoice\Application;

use App\Base\BaseController;
use App\Billing\Domain\BillPdfUseCase;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\InvoiceCreateUseCase;
use App\Invoice\Domain\InvoiceDeleteUseCase;
use App\Invoice\Domain\InvoiceGetAllUseCase;
use App\Invoice\Domain\InvoiceUpdateUseCase;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends BaseController
{
    #[Route('/invoice', name: 'invoice_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('invoice/index.html.twig');
    }

    #[Route('/invoice/get', name: 'invoice_list', methods: ['POST'])]
    public function getAll(InvoiceGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/invoice/add', name: 'invoice_create', methods: ['POST'])]
    public function add(Request $request, InvoiceCreateUseCase $createUseCase): Response
    {
        /**
         * @var Invoice $invoice
         */
        $invoice = $this->serializer->deserialize(json_encode($request->request->all()), Invoice::class, 'json');
        $createUseCase($invoice);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/invoice/edit/{id}', name: 'invoice_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, InvoiceUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['comprobante_id' => $id]);
        /**
         * @var Invoice $invoice
         */
        $invoice = $this->serializer->deserialize(json_encode($request->request->all()), Invoice::class, 'json');
        $updateUseCase($invoice);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/invoice/delete/{id}', name: 'invoice_delete', methods: ['DELETE', 'POST'])]
    public function delete(InvoiceDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    #[Route('/invoice/{id}/pdf', name: 'invoice_pdf', methods: ['GET'])]
    public function pdf(Request $request, BillPdfUseCase $billPdfUseCase, $id): StreamedResponse
    {
        $preview = $request->query->get('preview', false);

        $fileResponse = $billPdfUseCase($id);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($fileResponse) {
            echo $fileResponse->file;
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $fileResponse->name . '"');

        if (!$preview) {
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileResponse->name . '"');
        }

        return $response;
    }
}
