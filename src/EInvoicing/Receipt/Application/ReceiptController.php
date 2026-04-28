<?php

declare(strict_types=1);

namespace App\EInvoicing\Receipt\Application;

use App\Core\Base\BaseController;
use App\Core\Exception\ServiceException;
use App\Efact\Service\PdfExporter;
use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\EInvoicing\Receipt\Domain\ReceiptCdrUseCase;
use App\EInvoicing\Receipt\Domain\ReceiptPdfUseCase;
use App\EInvoicing\Receipt\Domain\ReceiptRequest;
use App\EInvoicing\Receipt\Domain\ReceiptShowUseCase;
use App\EInvoicing\Receipt\Domain\ReceiptType;
use App\EInvoicing\Receipt\Domain\ReceiptXmlUseCase;
use App\Repository\InvoiceRepository;
use App\Billing\Repository\TaxDocumentRepository;
use App\Sale\Domain\DocType;
use Brick\Math\Exception\MathException;
use chillerlan\QRCode\QRCode;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class ReceiptController extends BaseController
{
    private const array CURRENCY_SYMBOLS = [
        'PEN' => 'S/',
        'USD' => '$',
        'EUR' => '€',
    ];

    /**
     * @throws ServiceException
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
            'currency_symbol' => self::CURRENCY_SYMBOLS[$receipt->currency] ?? $receipt->currency,
            'pdf' => $this->generateUrl('receipt_pdf', ['payload' => $payload]),
            'xml' => $this->generateUrl('receipt_xml', ['payload' => $payload]),
            'cdr' => $this->generateUrl('receipt_cdr', ['payload' => $payload]),
        ]);
    }

    /**
     * @throws Exception
     * @throws MathException
     */
    #[Route('/receipt/{id}/ticket', name: 'receipt_ticket', methods: ['GET'])]
    public function ticket(
        Request $request,
        string $id,
        TaxDocumentRepository $taxDocumentRepository,
        SettingsService $settingsService,
    ): Response {
        $voucher = $taxDocumentRepository->getVoucher($id);
        $qr = '';
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $qr = new QRCode()->render(strval($voucher->voucher_qr_data));
        }

        $shouldPrintLogo = 'true' === strval(
            $settingsService->get(Constants::PRINT_LOGO, 'true')
        );

        $format = strval($request->get('format', 'ticket'));
        $template = $format === 'a4'
            ? 'invoice/invoice_a4.html.twig'
            : 'invoice/invoice.html.twig';
        $content = $this->renderView($template, [
            'qr' => $qr,
            'voucher' => $voucher,
            'extraData' => null,
            'printLogo' => $shouldPrintLogo,
        ]);

        $preview = $request->get('preview');

        if ($preview) {
            return new Response($content);
        }

        $pdfExporter = new PdfExporter();

        return $pdfExporter->export($content, 'invoice');
    }

    /**
     * @throws FilesystemException
     */
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

    /**
     * @throws FilesystemException
     */
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

        if (!$receiptType instanceof ReceiptType) {
            throw new ServiceException('Invalid receipt type');
        }

        return new ReceiptRequest(type: $receiptType, id: $decoded['id']);
    }
}
