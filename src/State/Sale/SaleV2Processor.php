<?php

declare(strict_types=1);

namespace App\State\Sale;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SaleItem;
use App\Entity\SaleRecord;
use App\Repository\ProductRepository;
use App\Repository\SaleRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Processor for POST/PATCH /api/sales.
 *
 * Hydrates the incoming SaleRecord with pipeline-only data, delegates all
 * business logic to SaleProcessor, then returns a refreshed SaleRecord.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class SaleV2Processor implements ProcessorInterface
{
    public function __construct(
        private SaleProcessor $saleProcessor,
        private SaleRecordRepository $saleRecordRepository,
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): SaleRecord {
        if (!$data instanceof SaleRecord) {
            throw new \InvalidArgumentException('SaleV2Processor expects a SaleRecord instance.');
        }

        // Hydrate pipeline-only fields not set by deserializer
        $data->setTipoId(2);
        if ($data->getBranch() === null && $data->getWarehouse()?->getBranch() !== null) {
            $data->setBranch($data->getWarehouse()->getBranch());
        }

        // Build SaleItem entities and attach them for SaleProcessor
        foreach ($this->buildSaleItems($data) as $item) {
            $data->addLegacyItem($item);
        }

        $this->saleProcessor->process($data, $operation, $uriVariables, $context);

        // Refresh from DB to get fully populated SaleRecord with V2 data
        $saleId = $data->getId();
        if ($saleId === null) {
            throw new \RuntimeException('SaleProcessor did not assign an ID to the sale.');
        }

        $this->entityManager->clear();
        $record = $this->saleRecordRepository->findByAnyId($saleId);

        if ($record === null) {
            // In v2_authoritative mode the sale is written only to `sales` table.
            // If it's not found yet (possible race), return a minimal hydrated SaleRecord.
            $this->logger->warning('SaleRecord not found after processing, returning synthetic record.', [
                'sale_id' => $saleId,
            ]);
            $data->setId($saleId);

            return $data;
        }

        $this->saleRecordRepository->enrichWithTaxDocuments([$record]);

        return $record;
    }

    /**
     * Convert the raw writeItems array into SaleItem entities.
     *
     * @return SaleItem[]
     */
    private function buildSaleItems(SaleRecord $record): array
    {
        $items = [];

        foreach ($record->getWriteItems() as $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            $productId = $this->extractProductId($itemData);
            if ($productId === null) {
                continue;
            }

            $product = $this->productRepository->find($productId);
            if ($product === null) {
                $this->logger->warning('Product not found for SaleRecord item', ['product_id' => $productId]);
                continue;
            }

            $item = new SaleItem();
            $item->setProducto($product);
            $item->setCantidad((float) ($itemData['qty'] ?? $itemData['cantidad'] ?? 0));
            $item->setPrecioUnitario((string) ($itemData['unitPrice'] ?? $itemData['precio_unitario'] ?? '0'));
            $item->setPrecioTotal((string) ($itemData['netAmount'] ?? $itemData['precio_total'] ?? '0'));
            $item->setDescuento((string) ($itemData['discountAmount'] ?? $itemData['descuento'] ?? '0'));

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Extract product ID from various formats sent by the form:
     * - IRI string: "/api/products/uuid"
     * - Object with @id: {"@id": "/api/products/uuid", ...}
     * - Object with id: {"id": "uuid", ...}
     * - Plain scalar: "uuid"
     *
     * @param array<string, mixed> $itemData
     */
    private function extractProductId(array $itemData): ?string
    {
        $productRef = $itemData['product'] ?? $itemData['producto'] ?? null;

        if ($productRef === null) {
            return null;
        }

        if (is_string($productRef)) {
            return basename($productRef);
        }

        if (is_array($productRef)) {
            if (isset($productRef['id'])) {
                return (string) $productRef['id'];
            }
            if (isset($productRef['@id'])) {
                return basename((string) $productRef['@id']);
            }
        }

        return null;
    }
}
