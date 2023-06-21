<?php

namespace App\Receipt\Application;

use App\Base\BaseController;
use App\Billing\Domain\BillPdfUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Receipt\Domain\ReceiptRequest;
use App\Receipt\Domain\ReceiptShowUseCase;
use App\Receipt\Domain\ReceiptType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class ReceiptController extends BaseController
{
    public function __construct(
        private readonly TenantAwareSettingsService $settings,
        private readonly FileManager $fileManager
    ) {
        parent::__construct();
    }

    #[Route('/receipt/{payload}', name: 'receipt_show', methods: ['GET'])]
    public function show(ReceiptShowUseCase $receiptShowUseCase, string $payload): Response
    {
        $decoded = json_decode(base64_decode($payload), true);
        $receiptRequest = new ReceiptRequest(type: ReceiptType::tryFrom($decoded['type']), id: $decoded['id']);

        $content = $receiptShowUseCase($receiptRequest);

        return new Response($content);
    }

    #[Route('/receipt/{id}/xml', name: 'receipt_xml', methods: ['GET'])]
    public function xml(GetOneVoucherUseCase $getOneById, string $id): StreamedResponse
    {
        $voucher = $getOneById($id);

        $docName = $voucher->voucher_series.'-'.$voucher->voucher_number;
        $docFullName = $voucher->company_id.'-'.$voucher->voucher_type.'-'.$docName;

        $xmlPath = $this->settings->get(Constants::COMPANY_ID).'/'.$docFullName.'.xml';

        $response = new StreamedResponse(function () use ($xmlPath) {
            echo $this->fileManager->read($xmlPath);
        });

        $response->headers->set('Content-Type', 'text/xml');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$docFullName.'.xml"');

        return $response;
    }

    #[Route('/receipt/{id}/cdr', name: 'receipt_cdr', methods: ['GET'])]
    public function cdr(GetOneVoucherUseCase $getOneById, string $id): StreamedResponse
    {
        $voucher = $getOneById($id);

        $docName = $voucher->voucher_series.'-'.$voucher->voucher_number;
        $docFullName = $voucher->company_id.'-'.$voucher->voucher_type.'-'.$docName;

        $cdrPath = $this->settings->get(Constants::COMPANY_ID).'/R-'.$docFullName.'.zip';

        $response = new StreamedResponse(function () use ($cdrPath) {
            echo $this->fileManager->read($cdrPath);
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="R-'.$docFullName.'.zip"');

        return $response;
    }

    #[Route('/receipt/{id}/pdf', name: 'receipt_pdf', methods: ['GET'])]
    public function pdf(Request $request, BillPdfUseCase $billPdfUseCase, string $id): StreamedResponse
    {
        $preview = (bool) $request->query->get('preview', false);

        $fileResponse = $billPdfUseCase($id);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($fileResponse) {
            echo $fileResponse->file;
        });

        $response->headers->set('Content-Type', 'application/pdf');

        if ($preview) {
            $response->headers->set('Content-Disposition', 'inline; filename="'.$fileResponse->name.'"');
        } else {
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$fileResponse->name.'"');
        }

        return $response;
    }
}
