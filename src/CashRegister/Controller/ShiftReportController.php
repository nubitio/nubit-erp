<?php

declare(strict_types=1);

namespace App\CashRegister\Controller;

use App\CashRegister\Service\ShiftReport;
use App\CashRegister\Service\ShiftReportEscPos;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The data behind the X cut and the Z report of a shift, as the till prints them. */
final class ShiftReportController extends AbstractController
{
    private const array PAPER_WIDTH_MAP = [58 => 32, 80 => 48];

    public function __construct(
        private readonly ShiftReport $report,
        private readonly ShiftReportEscPos $escPos,
        private readonly Connection $connection,
        private readonly Security $security,
        #[Autowire('%env(PRINTER_LINE_WIDTH)%')]
        private readonly int $printerLineWidth,
        private readonly BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @throws Exception
     */
    #[IsGranted('perm:cash.view')]
    #[Route(
        '/api/cash-sessions/{id}/report',
        name: 'cash_session_report',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function __invoke(int $id): JsonResponse
    {
        return $this->json($this->report->forSession($id, $this->requireBranch()));
    }

    /**
     * The same report as ESC/POS commands for the till printer. The cashier prints their own shift; seeing another
     * person's needs the permission to close any shift.
     *
     * @throws Exception
     */
    #[IsGranted('perm:cash.view')]
    #[Route(
        '/api/cash-sessions/{id}/print-report',
        name: 'cash_session_print_report',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function print(int $id, Request $request): Response
    {
        $branchId = $this->requireBranch();
        $ownerId = $this->connection->fetchOne('SELECT opened_by FROM cash_sessions WHERE id = ? AND branch_id = ?', [
            $id,
            $branchId,
        ]);
        $user = $this->security->connectedUser();
        if (
            $ownerId !== false
            && $user !== null
            && (int) $ownerId !== $user->userId
            && !$this->security->hasPermission('cash.close_any')
        ) {
            throw new AccessDeniedHttpException('Solo puedes imprimir el reporte de tu propio turno.');
        }

        $width = self::PAPER_WIDTH_MAP[$request->query->getInt('paper_width')] ?? max(32, $this->printerLineWidth);

        return new Response(
            $this->escPos->commands($this->report->forSession($id, $branchId), $width),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/octet-stream',
            ],
        );
    }

    private function requireBranch(): string
    {
        return $this->branchContext->getCurrentBranchId() ?? throw new NotFoundHttpException('Turno no encontrado.');
    }
}
