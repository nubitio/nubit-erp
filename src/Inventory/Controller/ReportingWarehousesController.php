<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Identity\Auth\BranchReportScope;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class ReportingWarehousesController
{
    public function __construct(private BranchReportScope $scope, private Connection $connection) {}

    #[Route('/api/inventory/reporting-warehouses', methods: ['GET'])]
    #[IsGranted('perm:inventory.view')]
    public function __invoke(Request $request): JsonResponse
    {
        $id = $this->scope->requireBranchId($request->query->getString('branchId') ?: null);

        return new JsonResponse($this->connection->fetchAllAssociative(
            'SELECT almacen_id AS id, descripcion AS name FROM almacen WHERE local_id = ? ORDER BY descripcion',
            [$id],
        ));
    }
}
