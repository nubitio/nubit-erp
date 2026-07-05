<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\Efact\Audit\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/api/control-plane/audit-log')]
final class AuditLogApiController extends AbstractController
{
    private const int PAGE_LIMIT = 50;

    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    #[Route('', name: 'api_control_plane_audit_log', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'action' => $request->query->getString('action'),
            'date_from' => $request->query->getString('date_from'),
            'date_to' => $request->query->getString('date_to'),
        ];

        $page = max(1, $request->query->getInt('page', 1));
        $result = $this->auditLogRepository->getAuditLogs($filters, $page, self::PAGE_LIMIT);
        $totalPages = $result['total'] > 0 ? (int) ceil($result['total'] / self::PAGE_LIMIT) : 1;

        return $this->json([
            'logs' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => self::PAGE_LIMIT,
            'filters' => $filters,
            'actions' => $this->auditLogRepository->getDistinctActions(),
        ]);
    }
}
