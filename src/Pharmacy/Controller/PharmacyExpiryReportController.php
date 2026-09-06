<?php

declare(strict_types=1);

namespace App\Pharmacy\Controller;

use App\Pharmacy\Repository\PharmacyBatchRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pharmacy-only read: batches at or past their expiry within a window
 * (default 30 days). A vertical-specific view the ERP core does not provide.
 */
#[Route('/api/pharmacy/expiry-report', name: 'api_pharmacy_expiry_report', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class PharmacyExpiryReportController extends AbstractController
{
    private const int MAX_WINDOW_DAYS = 365;

    public function __construct(
        private readonly PharmacyBatchRepository $batches,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $days = (int) $request->query->get('days', '30');
        $days = max(0, min($days, self::MAX_WINDOW_DAYS));
        $today = new DateTimeImmutable('today');
        $cutoff = $today->modify(sprintf('+%d days', $days));

        $items = [];
        foreach ($this->batches->expiringBy($cutoff) as $batch) {
            $items[] = [
                'productId' => $batch->getProductId(),
                'batchNumber' => $batch->getBatchNumber(),
                'expirationDate' => $batch->getExpirationDate()->format('Y-m-d'),
                'expired' => $batch->isExpiredOn($today),
                'manufacturer' => $batch->getManufacturer(),
            ];
        }

        return $this->json(['windowDays' => $days, 'count' => count($items), 'items' => $items]);
    }
}
