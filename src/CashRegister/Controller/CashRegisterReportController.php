<?php

declare(strict_types=1);

namespace App\CashRegister\Controller;

use App\CashRegister\Service\SessionSalesBreakdown;
use App\CashRegister\Service\ShiftCash;
use App\CashRegister\Service\ShiftReceipts;
use App\CashRegister\Service\ShiftReport;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Nubit\Platform\Export\PdfExporter;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

class CashRegisterReportController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly PdfExporter $pdfExporter,
        private readonly BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @throws Exception
     */
    #[IsGranted('perm:cash.view')]
    #[RequiresFeature('pdf_bulk_download')]
    #[Route('/api/cash-sessions/{id}/closure-report', name: 'cash_session_closure_report', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        $report = new ShiftReport(
            $this->connection,
            new SessionSalesBreakdown($this->connection),
            new ShiftReceipts($this->connection),
            new ShiftCash($this->connection),
        );

        try {
            $branchId = $this->branchContext->getCurrentBranchId() ?? throw new NotFoundHttpException();
            $data = $report->forSession($id, $branchId);
        } catch (NotFoundHttpException) {
            return $this->json(['message' => 'Turno no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $content = $this->twig->render('cash_register/closure_report.html.twig', ['report' => $data]);

        return $this->pdfExporter->export($content, sprintf('cierre-caja-%d', $id));
    }
}
