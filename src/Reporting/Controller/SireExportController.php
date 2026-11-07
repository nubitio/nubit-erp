<?php

declare(strict_types=1);

namespace App\Reporting\Controller;

use App\Reporting\Sire\RvieExporter;
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
 */
#[Route('/api/v1/sire')]
#[IsGranted('ROLE_ADMIN')]
final class SireExportController extends AbstractController
{
    public function __construct(
        private readonly RvieExporter $rvieExporter,
    ) {}

    #[Route('/rvie/preview', name: 'sire_rvie_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $result = $this->rvieExporter->export($this->period($request));

        return $this->json([
            'fileName' => $result['zipName'],
            'recordCount' => $result['recordCount'],
            'excluded' => $result['excluded'],
            'warnings' => $result['warnings'],
        ]);
    }

    #[Route('/rvie', name: 'sire_rvie', methods: ['GET'])]
    public function rvie(Request $request): Response
    {
        $result = $this->rvieExporter->export($this->period($request));
        if ($result['recordCount'] === 0) {
            throw new BadRequestHttpException('No hay comprobantes de venta para el periodo seleccionado.');
        }

        $asText = $request->query->get('format') === 'txt';
        $response = new Response($asText ? $result['content'] : $result['zip']);
        $response->headers->set('Content-Type', $asText ? 'text/plain; charset=us-ascii' : 'application/zip');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="%s"',
            $asText ? $result['txtName'] : $result['zipName'],
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
