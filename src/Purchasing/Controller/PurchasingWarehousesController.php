<?php

declare(strict_types=1);

namespace App\Purchasing\Controller;

use App\Purchasing\State\PurchasingBranchScope;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PurchasingWarehousesController
{
    public function __construct(
        private PurchasingBranchScope $scope,
        private Connection $connection,
    ) {}

    #[Route('/api/purchasing/warehouses', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $branchId = $request->query->getString('branchId');
        $this->scope->authorize($branchId);
        $rows = $this->connection->fetchAllAssociative('SELECT almacen_id AS id, descripcion AS name FROM almacen WHERE local_id = ? AND is_active = 1 ORDER BY descripcion', [
            $branchId,
        ]);

        return new JsonResponse(array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            '@id' => '/api/warehouses/' . rawurlencode((string) $row['id']),
        ], $rows));
    }
}
