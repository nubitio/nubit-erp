<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Sales\Receivable\CollectionStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/sales/reports/receivable-customers/{customerId}/collections: the customer's latest collections, for the
 * history and the undo action. It lives under `reports/` because `/api/sales/{id}` would swallow a bare path.
 */
final class CollectionListController extends AbstractController
{
    public function __construct(
        private readonly CollectionStore $store,
    ) {}

    #[Route(
        '/api/sales/reports/receivable-customers/{customerId<\d+>}/collections',
        name: 'sales_collection_list',
        methods: ['GET'],
    )]
    #[IsGranted('perm:sales.view')]
    public function list(int $customerId, Request $request): JsonResponse
    {
        return $this->json($this->store->recentFor($customerId, $request->query->getInt('limit', 10)));
    }
}
