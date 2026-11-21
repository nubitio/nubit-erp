<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Service\ProductExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/products')]
final class ProductExportController extends AbstractController
{
    public function __construct(
        private readonly ProductExportService $exportService,
    ) {}

    /**
     * GET /api/products/export
     * Streams all current products as a .xlsx file — same column layout as
     * the import template so users can edit and re-import seamlessly.
     */
    #[IsGranted('perm:catalog.view')]
    #[Route('/export', name: 'product_export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $spreadsheet = $this->exportService->build();
        $filename = 'productos-' . date('Y-m-d') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            $this->exportService->stream($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
