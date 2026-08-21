<?php

declare(strict_types=1);

namespace App\Reporting\Controller;

use App\Reporting\Ple\AccountingBookExporter;
use App\Reporting\Ple\PlePreviewService;
use App\Reporting\Ple\PleValidationService;
use App\Reporting\Ple\PleZipService;
use App\Reporting\Ple\PurchaseLedgerExporter;
use App\Reporting\Ple\SalesLedgerExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Descarga de los Libros Electrónicos (PLE) de SUNAT en formato de texto:
 *   GET /api/v1/ple/sales?period=AAAAMM     → Registro de Ventas (14.1)
 *   GET /api/v1/ple/purchases?period=AAAAMM  → Registro de Compras (8.1)
 *   GET /api/v1/ple/diary?period=AAAAMM     → Libro Diario (5.1)
 *   GET /api/v1/ple/ledger?period=AAAAMM    → Libro Mayor (6.1)
 */
#[Route('/api/v1/ple')]
#[IsGranted('ROLE_ADMIN')]
final class PleExportController extends AbstractController
{
    private const array BOOK_KEYS = ['sales', 'purchases', 'diary', 'ledger'];

    public function __construct(
        private readonly SalesLedgerExporter $salesExporter,
        private readonly PurchaseLedgerExporter $purchaseExporter,
        private readonly AccountingBookExporter $accountingExporter,
        private readonly PlePreviewService $previewService,
        private readonly PleValidationService $validationService,
        private readonly PleZipService $zipService,
    ) {}

    #[Route('/preview', name: 'ple_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request);

        return $this->json($this->previewService->preview($period));
    }

    #[Route('/validate', name: 'ple_validate', methods: ['GET'])]
    public function validate(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $book = $request->query->get('book');
        $bookKey = is_string($book) && $book !== '' ? $book : null;

        if ($bookKey !== null && !in_array($bookKey, self::BOOK_KEYS, true)) {
            throw $this->createNotFoundException('El libro solicitado no es válido.');
        }

        return $this->json($this->validationService->validate($period, $bookKey));
    }

    #[Route('/zip', name: 'ple_zip', methods: ['GET'])]
    public function zip(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        return $this->zipService->createArchive($period);
    }

    #[Route('/sales', name: 'ple_sales', methods: ['GET'])]
    public function sales(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        return $this->download($this->salesExporter->export($period));
    }

    #[Route('/purchases', name: 'ple_purchases', methods: ['GET'])]
    public function purchases(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        return $this->download($this->purchaseExporter->export($period));
    }

    #[Route('/diary', name: 'ple_diary', methods: ['GET'])]
    public function diary(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        return $this->download($this->accountingExporter->exportDiary($period));
    }

    #[Route('/ledger', name: 'ple_ledger', methods: ['GET'])]
    public function ledger(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        return $this->download($this->accountingExporter->exportMayor($period));
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query->get('period', '');
        if (!preg_match('/^\d{6}$/', $period)) {
            throw $this->createNotFoundException('El parámetro «period» debe tener el formato AAAAMM.');
        }

        $month = (int) substr($period, 4, 2);
        if ($month < 1 || $month > 12) {
            throw $this->createNotFoundException('El mes del periodo es inválido.');
        }

        return $period;
    }

    /**
     * @param array{filename: string, content: string} $result
     */
    private function download(array $result): Response
    {
        $response = new Response($result['content']);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
