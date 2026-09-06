<?php

declare(strict_types=1);

namespace App\Pharmacy\Service;

use App\Pharmacy\Entity\PharmacyBatch;
use App\Pharmacy\Entity\PharmacyProduct;
use App\Pharmacy\Enum\ExpiryControl;
use App\Pharmacy\Repository\PharmacyBatchRepository;
use App\Pharmacy\Repository\PharmacyProductRepository;
use DateTimeImmutable;

/**
 * Read seam over the pharmacy context (NB-10). The FEFO / expiry-blocking stock
 * policy (NB-11) and the sale-confirmed reaction (NB-12) build on these queries
 * rather than touching the repositories directly.
 */
final readonly class PharmacyCatalogService
{
    public function __construct(
        private PharmacyProductRepository $products,
        private PharmacyBatchRepository $batches,
    ) {}

    public function profileFor(int $productId): ?PharmacyProduct
    {
        return $this->products->findForProduct($productId);
    }

    public function requiresPrescription(int $productId): bool
    {
        return $this->products->findForProduct($productId)?->requiresPrescription() ?? false;
    }

    public function expiryControlFor(int $productId): ExpiryControl
    {
        return $this->products->findForProduct($productId)?->getExpiryControl() ?? ExpiryControl::None;
    }

    /**
     * Batches for a product in first-expiry-first order.
     *
     * @return list<PharmacyBatch>
     */
    public function fefoBatches(int $productId): array
    {
        return $this->batches->fefoForProduct($productId);
    }

    /**
     * The earliest non-expired batch for a product, or null when none applies.
     */
    public function nextUsableBatch(int $productId, DateTimeImmutable $on): ?PharmacyBatch
    {
        foreach ($this->batches->fefoForProduct($productId) as $batch) {
            if (!$batch->isExpiredOn($on)) {
                return $batch;
            }
        }

        return null;
    }
}
