<?php

namespace App\Receipt\Application;

use App\Core\Base\BaseController;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Infrastructure\Service\PdfExporter;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Receipt\Domain\ReceiptCdrUseCase;
use App\Receipt\Domain\ReceiptPdfUseCase;
use App\Receipt\Domain\ReceiptRequest;
use App\Receipt\Domain\ReceiptShowUseCase;
use App\Receipt\Domain\ReceiptType;
use App\Receipt\Domain\ReceiptXmlUseCase;
use App\Sale\Domain\DocType;
use chillerlan\QRCode\QRCode;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class ReceiptController extends BaseController
{
    /**
     * @throws ServiceException
     * @throws Exception
     */
    #[Route('/receipt/{payload}', name: 'receipt_show', methods: ['GET'])]
    public function show(ReceiptShowUseCase $receiptShowUseCase, string $payload): Response
    {
        $receipt = $receiptShowUseCase($this->getReceiptRequest($payload));

        return $this->render('receipt/show.html.twig', [
            'company_name' => $receipt->company,
            'type' => $receipt->type,
            'series' => $receipt->series,
            'number' => $receipt->number,
            'date' => date('d/m/Y', intval(strtotime($receipt->date))),
            'total' => $receipt->total,
            'pdf' => $this->generateUrl('receipt_pdf', ['payload' => $payload]),
            'xml' => $this->generateUrl('receipt_xml', ['payload' => $payload]),
            'cdr' => $this->generateUrl('receipt_cdr', ['payload' => $payload]),
        ]);
    }

    #[Route('/receipt/{id}/ticket', name: 'receipt_ticket', methods: ['GET'])]
    public function ticket(
        Request $request,
        string $id,
        InvoiceRepositoryInterface $invoiceRepository,
    ): Response {
        $voucher = $invoiceRepository->getVoucher($id);
        $qr = '';
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $qr = (new QRCode())->render(strval($voucher->voucher_qr_data));
        }

        $template = 'sale/report/invoice.html.twig';
        $content = $this->renderView($template, [
            'qr' => $qr,
            'voucher' => $voucher,
            'extraData' => null,
        ]);

        $preview = $request->get('preview');

        if ($preview) {
            return new Response($content);
        }

        $pdfExporter = new PdfExporter();

        return $pdfExporter->export($content, 'invoice');
    }

    #[Route('/receipt/{payload}/xml', name: 'receipt_xml', methods: ['GET'])]
    public function xml(
        ReceiptXmlUseCase $receiptXmlUseCase,
        string $payload,
    ): StreamedResponse {
        $fileResponse = $receiptXmlUseCase($this->getReceiptRequest($payload));

        $response = new StreamedResponse(function () use ($fileResponse): void {
            echo $fileResponse->file;
        });

        $response->headers->set('Content-Type', 'text/xml');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $fileResponse->name . '"'
        );

        return $response;
    }

    #[Route('/receipt/{payload}/cdr', name: 'receipt_cdr', methods: ['GET'])]
    public function cdr(
        ReceiptCdrUseCase $receiptCdrUseCase,
        string $payload,
    ): StreamedResponse {
        $fileResponse = $receiptCdrUseCase($this->getReceiptRequest($payload));

        $response = new StreamedResponse(function () use ($fileResponse): void {
            echo $fileResponse->file;
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $fileResponse->name . '"'
        );

        return $response;
    }

    /**
     * @throws ServiceException
     */
    #[Route('/receipt/{payload}/pdf', name: 'receipt_pdf', methods: ['GET'])]
    public function pdf(
        Request $request,
        ReceiptPdfUseCase $receiptPdfUseCase,
        string $payload,
    ): StreamedResponse {
        $preview = (bool)$request->query->get('preview', false);

        $fileResponse = $receiptPdfUseCase($this->getReceiptRequest($payload));

        $response = new StreamedResponse();
        $response->setCallback(function () use ($fileResponse): void {
            echo $fileResponse->file;
        });

        $response->headers->set('Content-Type', 'application/pdf');

        if ($preview) {
            $response->headers->set(
                'Content-Disposition',
                'inline; filename="' . $fileResponse->name . '"'
            );
        } else {
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . $fileResponse->name . '"'
            );
        }

        return $response;
    }

    /**
     * @throws ServiceException
     */
    private function getReceiptRequest(string $payload): ReceiptRequest
    {
        /** @var array<string, string> $decoded */
        $decoded = json_decode(base64_decode($payload), true);

        $receiptType = ReceiptType::tryFrom($decoded['type']);

        if (!$receiptType instanceof \App\Receipt\Domain\ReceiptType) {
            throw new ServiceException('Invalid receipt type');
        }

        return new ReceiptRequest(type: $receiptType, id: $decoded['id']);
    }
}
