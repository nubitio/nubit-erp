<?php

namespace App\Invoice\Application;

use App\Base\BaseController;
use App\Billing\Domain\BillPdfUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\InvoiceConsultUseCase;
use App\Invoice\Domain\InvoiceEmailUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends BaseController
{
    public function __construct(
        private readonly TenantAwareSettingsService $settings,
        private readonly FileManager                $fileManager
    )
    {
        parent::__construct();
    }

    #[Route('/invoice', name: 'invoice_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('invoice/index.html.twig');
    }

    #[Route('/invoice/{id}', name: 'invoice_show', methods: ['GET'])]
    public function show(GetOneVoucherUseCase $getOneById, string $id): Response
    {
        $voucher = $getOneById($id);

        $type = match ($voucher->voucher_type) {
            '01' => 'FACTURA',
            '03' => 'BOLETA',
            default => '',
        };

        return $this->render('invoice/show.html.twig', [
            'company_name' => $voucher->company_name,
            'type'         => $type,
            'series'       => $voucher->voucher_series,
            'number'       => $voucher->voucher_number,
            'date'         => date('d/m/Y', intval(strtotime(strval($voucher->voucher_date)))),
            'total'        => $voucher->voucher_sale_amount,
            'pdf'          => $this->generateUrl('invoice_pdf', ['id' => $id]),
            'xml'          => $this->generateUrl('invoice_xml', ['id' => $id]),
            'cdr'          => $this->generateUrl('invoice_cdr', ['id' => $id]),
        ]);
    }

    #[Route('/invoice/{id}/consult', name: 'invoice_consult', methods: ['GET'])]
    public function consult(InvoiceConsultUseCase $consultUseCase, string $id): Response
    {
        $consultUseCase($id);

        return $this->json(['success' => true, 'msg' => 'CRD actualizado']);
    }

    #[Route('/invoice/{id}/xml', name: 'invoice_xml', methods: ['GET'])]
    public function xml(GetOneVoucherUseCase $getOneById, string $id): StreamedResponse
    {
        $voucher = $getOneById($id);

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $docFullName = $voucher->company_id . '-' . $voucher->voucher_type . '-' . $docName;

        $xmlPath = $this->settings->get(Constants::COMPANY_ID) . '/' . $docFullName . '.xml';

        $respone = new StreamedResponse(function () use ($xmlPath) {
            echo $this->fileManager->read($xmlPath);
        });

        $respone->headers->set('Content-Type', 'text/xml');
        $respone->headers->set('Content-Disposition', 'attachment; filename="' . $docFullName . '.xml"');

        return $respone;
    }

    #[Route('/invoice/{id}/cdr', name: 'invoice_cdr', methods: ['GET'])]
    public function cdr(GetOneVoucherUseCase $getOneById, string $id): StreamedResponse
    {
        $voucher = $getOneById($id);

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $docFullName = $voucher->company_id . '-' . $voucher->voucher_type . '-' . $docName;

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
