<?php

declare(strict_types=1);

namespace App\Payroll\Controller;

use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollPlameExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Descarga del archivo de importación PLAME (Estructura 18 — Detalle de
 * Ingresos) para una corrida de planilla aprobada o pagada:
 *   GET /api/v1/payroll/{payrollRunId}/plame
 */
#[Route('/api/v1/payroll')]
#[IsGranted('ROLE_ADMIN')]
final class PayrollPlameExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PayrollPlameExportService $exportService,
    ) {}

    #[Route('/{payrollRunId}/plame', name: 'payroll_plame_export', methods: ['GET'])]
    public function export(string $payrollRunId): Response
    {
        $payrollRun = $this->entityManager->find(PayrollRun::class, $payrollRunId);
        if (!$payrollRun instanceof PayrollRun) {
            throw $this->createNotFoundException('Corrida de planilla no encontrada.');
        }

        if (!in_array($payrollRun->getStatus(), [PayrollRun::APPROVED, PayrollRun::PAID], strict: true)) {
            throw $this->createNotFoundException('La corrida debe estar aprobada o pagada para exportar PLAME.');
        }

        $result = $this->exportService->export($payrollRun);

        $response = new Response($result['content']);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
