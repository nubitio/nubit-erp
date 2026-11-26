<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class TransferDestinationsController
{
    public function __construct(
        private OperationalBranchGuard $branchGuard,
        private Connection $connection,
    ) {}

    #[Route('/api/inventory/transfer-destinations', methods: ['GET'])]
    #[IsGranted('perm:inventory.transfer')]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        $this->branchGuard->requireBranch();
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT w.almacen_id AS id, w.descripcion AS name, b.local_id AS branch_id, b.descripcion AS branch_name
            FROM almacen w JOIN local b ON b.local_id = w.local_id
            WHERE w.is_active = 1 AND b.is_active = 1
            ORDER BY b.descripcion, w.descripcion
            SQL);

        $destinations = [];
        foreach ($rows as $row) {
            if ($user->canOperateInBranch((string) $row['branch_id'])) {
                $destinations[] = [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                    'branchName' => (string) $row['branch_name'],
                ];
            }
        }

        return new JsonResponse($destinations);
    }
}
