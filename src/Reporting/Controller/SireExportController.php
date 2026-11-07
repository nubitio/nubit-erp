<?php

declare(strict_types=1);

namespace App\Reporting\Controller;

use App\Reporting\Sire\RceExporter;
use App\Reporting\Sire\RvieExporter;
use App\Reporting\Sire\SireExportResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Archivos de SIRE para «Reemplazar propuesta»:
 *   GET /api/v1/sire/rvie/preview?period=AAAAMM → resumen, excluidos y advertencias (JSON)
 *   GET /api/v1/sire/rvie?period=AAAAMM         → ZIP con el TXT del RVIE (ventas)
 *   GET /api/v1/sire/rvie?period=AAAAMM&format=txt → solo el TXT
 *   GET /api/v1/sire/rce/preview?period=AAAAMM  → igual, para el RCE (compras)
 *   GET /api/v1/sire/rce?period=AAAAMM[&format=txt] → ZIP o TXT del RCE
 */
#[Route('/api/v1/sire')]
#[IsGranted('ROLE_ADMIN')]
final class SireExportController extends AbstractController
{
    public function __construct(
        private readonly RvieExporter $rvieExporter,
        private readonly RceExporter $rceExporter,
    ) {}

    #[Route('/rvie/preview', name: 'sire_rvie_preview', methods: ['GET'])]
    public function rviePreview(Request $request): JsonResponse
    {
        return $this->preview($this->rvieExporter->export($this->period($request)));
    }

    #[Route('/rvie', name: 'sire_rvie', methods: ['GET'])]
    public function rvie(Request $request): Response
    {
        return $this->download(
            $this->rvieExporter->export($this->period($request)),
            $request,
            'No hay comprobantes de venta para el periodo seleccionado.',
        );
    }

    #[Route('/rce/preview', name: 'sire_rce_preview', methods: ['GET'])]
    public function rcePreview(Request $request): JsonResponse
    {
        return $this->preview($this->rceExporter->export($this->period($request)));
    }

    #[Route('/rce', name: 'sire_rce', methods: ['GET'])]
    public function rce(Request $request): Response
    {
        return $this->download(
            $this->rceExporter->export($this->period($request)),
            $request,
            'No hay compras con comprobante del proveedor para el periodo seleccionado.',
        );
    }

    private function preview(SireExportResult $result): JsonResponse
    {
        return $this->json([
            'fileName' => $result->zipName,
            'recordCount' => $result->recordCount,
            'excluded' => $result->excluded,
            'warnings' => $result->warnings,
        ]);
    }

    private function download(SireExportResult $result, Request $request, string $emptyMessage): Response
    {
        if ($result->recordCount === 0) {
            throw new BadRequestHttpException($emptyMessage);
        }

        $asText = $request->query->get('format') === 'txt';
        $response = new Response($asText ? $result->content : $result->zip);
        $response->headers->set('Content-Type', $asText ? 'text/plain; charset=us-ascii' : 'application/zip');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="%s"',
            $asText ? $result->txtName : $result->zipName,
        ));
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function period(Request $request): string
    {
        $period = (string) $request->query->get('period', '');
        if (preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new BadRequestHttpException('El parámetro «period» debe tener el formato AAAAMM.');
        }

        return $period;
    }
}
