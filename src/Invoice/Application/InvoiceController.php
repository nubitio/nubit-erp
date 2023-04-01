<?php

namespace App\Invoice\Application;

use App\Base\BaseController;
use App\Billing\Domain\BillPdfUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\InvoiceConsultUseCase;
use App\Invoice\Domain\InvoiceCreateUseCase;
use App\Invoice\Domain\InvoiceDeleteUseCase;
use App\Invoice\Domain\InvoiceEmailUseCase;
use App\Invoice\Domain\InvoiceGetAllUseCase;
use App\Invoice\Domain\InvoiceUpdateUseCase;
use App\Sale\Domain\SaleGetOneById;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends BaseController
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly FileManager     $fileManager
    )
    {
    }

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
    public function edit(Request $request, InvoiceUpdateUseCase $updateUseCase, string $id): Response
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
    public function delete(InvoiceDeleteUseCase $deleteUseCase, string $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    #[Route('/invoice/{id}', name: 'invoice_show', methods: ['GET'])]
    public function show(SaleGetOneById $getOneById, string $id): Response
    {
        $invoice = $getOneById($id);

        $type = match ($invoice['header']['doc_type']) {
            '01' => 'FACTURA',
            '03' => 'BOLETA',
            default => '',
        };

        return $this->render('invoice/show.html.twig', [
            'company_name' => $invoice['header']['company_name'],
            'type' => $type,
            'series' => $invoice['header']['doc_series'],
            'number' => $invoice['header']['doc_number'],
            'date' => date('d/m/Y', strtotime($invoice['header']['doc_date'])),
            'total' => $invoice['header']['doc_sale_amount'],
            'pdf' => $this->generateUrl('invoice_pdf', ['id' => $id]),
            'xml' => $this->generateUrl('invoice_xml', ['id' => $id]),
            'cdr' => $this->generateUrl('invoice_cdr', ['id' => $id]),
        ]);
    }

    #[Route('/invoice/{id}/consult', name: 'invoice_pdf', methods: ['GET'])]
    public function consult(InvoiceConsultUseCase $consultUseCase, string $id): Response
    {
        $consultUseCase($id);

        return $this->json(['success' => true, 'msg' => 'CRD actualizado']);
    }

    #[Route('/invoice/{id}/xml', name: 'invoice_xml', methods: ['GET'])]
    public function xml(SaleGetOneById $getOneById, string $id): StreamedResponse
    {
        $invoice = $getOneById($id);

        $docName = $invoice['header']['doc_series'] . '-' . $invoice['header']['doc_number'];
        $docFullName = $invoice['header']['company_id'] . '-' . $invoice['header']['doc_type'] . '-' . $docName;

        $xmlPath = $this->settings->get(Constants::COMPANY_ID) . '/' . $docFullName . '.xml';

        $respone = new StreamedResponse(function () use ($xmlPath) {
            echo $this->fileManager->read($xmlPath);
        });

        $respone->headers->set('Content-Type', 'text/xml');
        $respone->headers->set('Content-Disposition', 'attachment; filename="' . $docFullName . '.xml"');

        return $respone;
    }

    #[Route('/invoice/{id}/cdr', name: 'invoice_cdr', methods: ['GET'])]
    public function cdr(SaleGetOneById $getOneById, string $id): StreamedResponse
    {
        $invoice = $getOneById($id);

        $docName = $invoice['header']['doc_series'] . '-' . $invoice['header']['doc_number'];
        $docFullName = $invoice['header']['company_id'] . '-' . $invoice['header']['doc_type'] . '-' . $docName;

        $cdrPath = $this->settings->get(Constants::COMPANY_ID) . '/' . 'R-' . $docFullName . '.zip';

        $respone = new StreamedResponse(function () use ($cdrPath) {
            echo $this->fileManager->read($cdrPath);
        });

        $respone->headers->set('Content-Type', 'application/zip');
        $respone->headers->set('Content-Disposition', 'attachment; filename="R-' . $docFullName . '.zip"');

        return $respone;
    }

    #[Route('/invoice/{id}/pdf', name: 'invoice_pdf', methods: ['GET'])]
    public function pdf(Request $request, BillPdfUseCase $billPdfUseCase, string $id): StreamedResponse
    {
        $preview = (bool)$request->query->get('preview', false);

        $fileResponse = $billPdfUseCase($id);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($fileResponse) {
            echo $fileResponse->file;
        });

        $response->headers->set('Content-Type', 'application/pdf');

        if ($preview) {
            $response->headers->set('Content-Disposition', 'inline; filename="' . $fileResponse->name . '"');
        } else {
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileResponse->name . '"');
        }

        return $response;
    }

    #[Route('/invoice/{id}/email', name: 'invoice_email', methods: ['POST'])]
    public function sendEmail(InvoiceEmailUseCase $emailUseCase, string $id): Response
    {
        $emailUseCase($id);

        return $this->json(['success' => true, 'msg' => 'Email enviado']);
    }
}
