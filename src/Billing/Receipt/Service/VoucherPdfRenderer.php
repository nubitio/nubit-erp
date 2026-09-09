<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Service;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\FileResponse;
use App\Settings\Service\TenantSettingsService;
use chillerlan\QRCode\QRCode;
use Pontedilana\PhpWeasyPrint\Pdf;
use Twig\Environment;

/**
 * Produces the tenant-owned printable representation. It deliberately does
 * not call the SUNAT submission provider: PDFs are not fiscal source data.
 */
final readonly class VoucherPdfRenderer
{
    public function __construct(
        private TaxDocumentRepository $taxDocuments,
        private TenantSettingsService $tenantSettings,
        private Environment $twig,
        private string $weasyprintBinary,
    ) {}

    public function render(string $taxDocumentId): FileResponse
    {
        $voucher = $this->voucher($taxDocumentId);
        $qr = '';
        if ($voucher->voucher_type !== '00') {
            $qr = (string) new QRCode()->render((string) $voucher->voucher_qr_data);
        }
        $html = $this->twig->render('invoice/invoice_a4.html.twig', [
            'voucher' => $voucher,
            'qr' => $qr,
            'extraData' => null,
            'printLogo' => $this->tenantSettings->isPrintLogo(),
            'embedded' => false,
        ]);

        $pdf = new Pdf($this->weasyprintBinary);
        $pdf->setTimeout(30);

        return new FileResponse(
            $pdf->getOutputFromHtml($html),
            sprintf('%s-%s.pdf', $voucher->voucher_series, $voucher->voucher_number),
        );
    }

    public function voucher(string $taxDocumentId): Voucher
    {
        $taxDocument = $this->taxDocuments->find($taxDocumentId);

        return $taxDocument !== null && in_array($taxDocument->getDocumentType(), ['07', '08'], true)
            ? $this->taxDocuments->getNoteVoucher($taxDocumentId)
            : $this->taxDocuments->getVoucherByTaxDocumentId($taxDocumentId);
    }
}
