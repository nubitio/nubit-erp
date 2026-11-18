<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Api;

use App\Reporting\Financial\Application\Query\CashFlowQuery;
use App\Reporting\Financial\Application\Query\ProfitAndLossQuery;
use App\Reporting\Financial\Service\FinancialReportExportService;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/financial-reports')]
final class FinancialReportController extends AbstractController
{
    #[IsGranted('perm:reports.view')]
    #[Route('/profit-and-loss', methods: ['GET'])]
    public function profitAndLoss(Request $request, ProfitAndLossQuery $query): JsonResponse
    {
        $period = (string) $request->query->get('period', '');

        return $this->json($query->compute($period));
    }

    #[IsGranted('perm:reports.view')]
    #[Route('/cash-flow', methods: ['GET'])]
    public function cashFlow(Request $request, CashFlowQuery $query): JsonResponse
    {
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');
        $currency = (string) $request->query->get('currency', 'PEN');

        return $this->json($query->compute($from, $to, $currency));
    }

    #[RequiresFeature('excel_export')]
    #[IsGranted('perm:reports.export')]
    #[Route('/export.xlsx', methods: ['GET'])]
    public function export(
        Request $request,
        ProfitAndLossQuery $pnlQuery,
        CashFlowQuery $cashFlowQuery,
        FinancialReportExportService $exportService,
    ): StreamedResponse {
        $period = (string) $request->query->get('period', '');
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');

        $pnl = $pnlQuery->compute($period);
        $cashFlow = $cashFlowQuery->compute($from, $to);
        $spreadsheet = $exportService->buildSpreadsheet($pnl, $cashFlow);

        $filename = sprintf('reportes-financieros-%s.xlsx', $pnl['period']);

        $response = new StreamedResponse(static function () use ($exportService, $spreadsheet): void {
            $exportService->streamSpreadsheet($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
