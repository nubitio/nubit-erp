<?php

declare(strict_types=1);

namespace App\Pharmacy\Policy;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Pharmacy\Enum\ExpiryControl;
use App\Pharmacy\Service\PharmacyCatalogService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * NB-11: the pharmacy specialisation of the stock-validation seam. It decorates
 * whatever Inventory bound to {@see StockValidationPolicyInterface}, so Sales and
 * Inventory keep injecting the interface and never learn about Pharmacy — the
 * container swaps this in.
 *
 * On top of the base negative-stock check it enforces expiry per the product's
 * {@see ExpiryControl}: BLOCK rejects the sale when every batch is expired, WARN
 * surfaces a warning. Products with no pharmacy profile pass straight through.
 */
#[AsDecorator(StockValidationPolicyInterface::class)]
final readonly class PharmacyStockValidationPolicy implements StockValidationPolicyInterface
{
    public function __construct(
        #[AutowireDecorated]
        private StockValidationPolicyInterface $inner,
        private PharmacyCatalogService $pharmacy,
    ) {}

    public function validateSale(
        Product $product,
        string $warehouseId,
        string $requestedQtyBase,
        string $onHandQtyBase,
    ): StockValidationResult {
        $base = $this->inner->validateSale($product, $warehouseId, $requestedQtyBase, $onHandQtyBase);
        if (!$base->allowed) {
            return $base;
        }

        $productId = (int) $product->getId();
        $control = $this->pharmacy->expiryControlFor($productId);
        if ($control === ExpiryControl::None) {
            return $base;
        }

        $batches = $this->pharmacy->fefoBatches($productId);
        if ($batches === []) {
            // No batch data recorded yet — nothing to enforce.
            return $base;
        }

        $today = new DateTimeImmutable('today');
        foreach ($batches as $batch) {
            if (!$batch->isExpiredOn($today)) {
                return $base;
            }
        }

        return $control === ExpiryControl::Block
            ? StockValidationResult::blocked('Todos los lotes del medicamento están vencidos')
            : StockValidationResult::warning('Todos los lotes registrados del medicamento están vencidos');
    }
}
