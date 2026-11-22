<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Sales\Service\OfflinePriceReview;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** GET /api/sale-price-review — offline sales accepted at a price other than the current one. */
final class OfflinePriceReviewController extends AbstractController
{
    public function __construct(
        private readonly OfflinePriceReview $review,
    ) {}

    #[Route('/api/sale-price-review', name: 'sales_price_review', methods: ['GET'])]
    #[IsGranted('perm:reports.view')]
    public function __invoke(Request $request): JsonResponse
    {
        return $this->json($this->review->lines($request->query->getInt('limit', OfflinePriceReview::MAX_LINES)));
    }
}
