<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use App\Core\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('/admin/audit-log', name: 'admin_audit_log_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'action' => $request->query->getString('action'),
            'date_from' => $request->query->getString('date_from'),
            'date_to' => $request->query->getString('date_to'),
        ];

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;

        $result = $this->auditLogger->getAuditLogs($filters, $page, $limit);
        $actions = $this->auditLogger->getDistinctActions();

        $totalPages = $result['total'] > 0
            ? (int) ceil($result['total'] / $limit)
            : 1;

        return $this->render('admin/audit/index.html.twig', [
            'logs' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'actions' => $actions,
        ]);
    }
}
