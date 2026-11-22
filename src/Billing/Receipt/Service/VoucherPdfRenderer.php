<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Service;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\FileResponse;
use App\Settings\Service\TenantSettingsService;
use chillerlan\QRCode\QRCode;
use Dompdf\Dompdf;
use Dompdf\Options;
use Nubit\Platform\Filesystem\FileManager;
use Psr\Log\LoggerInterface;
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
        private FileManager $fileManager,
        private LoggerInterface $billingLogger,
    ) {}

    public function render(string $taxDocumentId): FileResponse
    {
        $voucher = $this->voucher($taxDocumentId);
        $qr = '';
        if ($voucher->voucher_type !== '00') {
            $qr = (string) new QRCode()->render((string) $voucher->voucher_qr_data);
        }
        $html = $this->twig->render('invoice/voucher_pdf.html.twig', [
            'voucher' => $voucher,
            'qr' => $qr,
            'extraData' => null,
            'printLogo' => $this->tenantSettings->isPrintLogo(),
        ]);

        $name = sprintf('%s-%s.pdf', $voucher->voucher_series, $voucher->voucher_number);

        // Rendering is the expensive step. The cache key is a hash of the
        // rendered HTML, so any change to the voucher, logo or settings
        // yields a new key and stale PDFs are never served.
        $location = sprintf('pdf-cache/%s/%s.pdf', $taxDocumentId, hash('sha256', $html));

        $cached = $this->readCache($location);
        if ($cached !== null) {
            return new FileResponse($cached, $name);
        }

        $bytes = $this->renderPdf($html);

        $this->writeCache($location, $bytes);

        return new FileResponse($bytes, $name);
    }

    /**
     * Dompdf runs in-process (no process spawn, no network), which is what
     * makes this ~10x faster than shelling out to WeasyPrint.
     */
    public function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false); // never fetch anything while rendering
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $cacheDir = sys_get_temp_dir() . '/dompdf';
        $options->set('tempDir', $cacheDir);
        $options->set('fontCache', $cacheDir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function readCache(string $location): ?string
    {
        try {
            return $this->fileManager->exists($location) ? $this->fileManager->read($location) : null;
        } catch (\Throwable $e) {
            $this->billingLogger->warning('voucher_pdf.cache_read_failed', [
                'location' => $location,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function writeCache(string $location, string $bytes): void
    {
        try {
            $this->fileManager->write($location, $bytes);
        } catch (\Throwable $e) {
            $this->billingLogger->warning('voucher_pdf.cache_write_failed', [
                'location' => $location,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function voucher(string $taxDocumentId): Voucher
    {
        $taxDocument = $this->taxDocuments->find($taxDocumentId);

        if ($taxDocument === null) {
            // The sales UI can request a ticket for a sale that has not yet
            // generated a fiscal document (for example, a nota de venta).
            return $this->taxDocuments->getVoucher($taxDocumentId);
        }

        return in_array($taxDocument->getDocumentType(), ['07', '08'], true)
            ? $this->taxDocuments->getNoteVoucher($taxDocumentId)
            : $this->taxDocuments->getVoucherByTaxDocumentId($taxDocumentId);
    }
}
