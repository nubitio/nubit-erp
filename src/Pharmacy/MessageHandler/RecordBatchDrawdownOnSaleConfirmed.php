<?php

declare(strict_types=1);

namespace App\Pharmacy\MessageHandler;

use App\Pharmacy\Entity\PharmacyBatchMovement;
use App\Pharmacy\Enum\ExpiryControl;
use App\Pharmacy\Repository\PharmacyBatchMovementRepository;
use App\Pharmacy\Service\PharmacyCatalogService;
use App\Sales\Event\SaleConfirmed;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * NB-12: Pharmacy reacts to {@see SaleConfirmed} to record which batch each line
 * drew down, FEFO. Sales never imports Pharmacy — this handler lives in the
 * vertical and subscribes to the Sales-owned event. Idempotent per sale.
 *
 * Lines for non-pharmacy products, products with no batches, or products whose
 * ExpiryControl is NONE are skipped. A line whose quantity exceeds the earliest
 * batch is still attributed to that batch — multi-batch splitting needs
 * Inventory's per-lot quantities and is a follow-up.
 */
#[AsMessageHandler]
final readonly class RecordBatchDrawdownOnSaleConfirmed
{
    public function __construct(
        private PharmacyCatalogService $pharmacy,
        private PharmacyBatchMovementRepository $movements,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(SaleConfirmed $event): void
    {
        if ($this->movements->existsForSale($event->saleId)) {
            return;
        }

        $now = new DateTimeImmutable();
        $recorded = false;

        foreach ($event->lines as $line) {
            $productId = (int) $line->productId;
            $profile = $this->pharmacy->profileFor($productId);
            if ($profile === null || $profile->getExpiryControl() === ExpiryControl::None) {
                continue;
            }

            $batch =
                $this->pharmacy->nextUsableBatch($productId, $now) ?? $this->pharmacy->fefoBatches($productId)[0]
                    ?? null;
            if ($batch === null) {
                continue;
            }

            $this->entityManager->persist(new PharmacyBatchMovement(
                batchId: (int) $batch->getId(),
                productId: $productId,
                saleId: $event->saleId,
                quantity: $line->quantity,
                occurredAt: $now,
            ));
            $recorded = true;
        }

        if ($recorded) {
            $this->entityManager->flush();
        }
    }
}
