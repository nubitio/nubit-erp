<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Inventory\Service\KardexExportService;
use Nubit\Platform\Export\PdfExporter;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class KardexExportController extends AbstractController
{
    public function __construct(
        private readonly KardexExportService $exportService,
        private readonly PdfExporter $pdfExporter,
    ) {}

    #[RequiresFeature('excel_export')]
    #[Route('/api/inventory/kardex/export.xlsx', name: 'api_inventory_kardex_export_xlsx', methods: ['GET'])]
    public function exportXlsx(Request $request): Response
    {
        return $this->buildXlsxResponse($request);
    }

    #[RequiresFeature('pdf_bulk_download')]
    #[Route('/api/inventory/kardex/export.pdf', name: 'api_inventory_kardex_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        return $this->buildPdfResponse($request);
    }

    private function buildXlsxResponse(Request $request): StreamedResponse
    {
        [$report, $filenameBase] = $this->loadReport($request);
        $spreadsheet = $this->exportService->buildSpreadsheet($report);
        $filename = $filenameBase . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            $this->exportService->streamSpreadsheet($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function buildPdfResponse(Request $request): StreamedResponse
    {
        [$report, $filenameBase] = $this->loadReport($request);
        $html = $this->exportService->renderPdfHtml($report);

        return $this->pdfExporter->export($html, $filenameBase);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function loadReport(Request $request): array
    {
        $productId = $this->resolveProductId($request);
        if ($productId === null) {
            throw new BadRequestHttpException('productId is required');
        }

        $warehouseId = $request->query->getString('warehouseId');
        $from = $request->query->getString('from');
        $to = $request->query->getString('to');
        $valuation = $request->query->getString('valuation');

        $report = $this->exportService->buildReportData([
            'productId' => $productId,
            'warehouseId' => $warehouseId !== '' ? $warehouseId : null,
            'from' => $from !== '' ? $from : null,
            'to' => $to !== '' ? $to : null,
            'valuation' => $valuation !== '' ? $valuation : null,
        ]);

        $filenameBase = sprintf('kardex-%s-%s', $report['summary']['product']['code'] ?? $productId, date('Y-m-d'));

        return [$report, $filenameBase];
    }

    private function resolveProductId(Request $request): ?string
    {
        $productId = $request->query->getString('productId');
        if ($productId !== '') {
            return $productId;
        }

        $product = $request->query->getString('product');
        if ($product === '') {
            return null;
        }

        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $product, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
