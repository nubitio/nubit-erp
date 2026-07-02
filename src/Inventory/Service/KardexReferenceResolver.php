<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\ApiResource\StockMovement;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Resolves stock movement source ids into human-readable document references
 * (serie-número de venta, compra, nota de crédito, etc.).
 */
final readonly class KardexReferenceResolver
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function resolve(?string $sourceType, ?string $sourceId): ?string
    {
        if ($sourceId === null || $sourceId === '') {
            return null;
        }

        $documentId = $this->extractDocumentId($sourceId);

        return match ($sourceType) {
            'LEGACY_MOVIMIENTO_ITEM' => $this->resolveSaleReference($documentId),
            'PURCHASE_ITEM', 'PURCHASE_VOID' => $this->resolvePurchaseReference($documentId),
            'CREDIT_NOTE' => $this->resolveTaxDocumentReference($documentId),
            'STOCK_ENTRY' => $this->resolveStockEntryReference($documentId),
            'PRODUCT_STOCK_TRANSFER' => $this->formatInternalReference('TR', null),
            'PRODUCT_STOCK_ADJUSTMENT' => 'Ajuste stock',
            'VOID' => $this->resolveVoidReference($sourceId),
            default => $this->fallbackLabel($sourceType),
        };
    }

    /**
     * @param list<StockMovement> $movements
     *
     * @throws Exception
     */
    public function applyToMovements(array $movements): void
    {
        if ($movements === []) {
            return;
        }

        $saleIds = [];
        $purchaseIds = [];
        $taxDocumentIds = [];
        $stockEntryIds = [];

        foreach ($movements as $movement) {
            $sourceType = $movement->sourceType;
            $sourceId = $movement->sourceId;
            if ($sourceId === null || $sourceId === '') {
                continue;
            }

            $documentId = $this->extractDocumentId($sourceId);

            match ($sourceType) {
                'LEGACY_MOVIMIENTO_ITEM' => $saleIds[$documentId] = true,
                'PURCHASE_ITEM', 'PURCHASE_VOID' => $purchaseIds[$documentId] = true,
                'CREDIT_NOTE' => $taxDocumentIds[$documentId] = true,
                'STOCK_ENTRY' => $stockEntryIds[$documentId] = true,
                default => null,
            };
        }

        $saleRefs = $this->fetchSaleReferences(array_keys($saleIds));
        $purchaseRefs = $this->fetchPurchaseReferences(array_keys($purchaseIds));
        $taxRefs = $this->fetchTaxDocumentReferences(array_keys($taxDocumentIds));
        $entryRefs = $this->fetchStockEntryReferences(array_keys($stockEntryIds));

        foreach ($movements as $movement) {
            $sourceType = $movement->sourceType;
            $sourceId = $movement->sourceId;
            if ($sourceId === null || $sourceId === '') {
                continue;
            }

            $documentId = $this->extractDocumentId($sourceId);
            $movement->sourceReference = match ($sourceType) {
                'LEGACY_MOVIMIENTO_ITEM' => $saleRefs[$documentId] ?? $this->resolveSaleReference($documentId),
                'PURCHASE_ITEM', 'PURCHASE_VOID' => $purchaseRefs[$documentId] ?? $this->resolvePurchaseReference(
                    $documentId,
                ),
                'CREDIT_NOTE' => $taxRefs[$documentId] ?? $this->resolveTaxDocumentReference($documentId),
                'STOCK_ENTRY' => $entryRefs[$documentId] ?? $this->resolveStockEntryReference($documentId),
                'PRODUCT_STOCK_TRANSFER' => $this->formatInternalReference('TR', null),
                'PRODUCT_STOCK_ADJUSTMENT' => 'Ajuste stock',
                'VOID' => $this->resolveVoidReference($sourceId),
                default => $this->fallbackLabel($sourceType),
            };
        }
    }

    private function extractDocumentId(string $sourceId): string
    {
        if (str_starts_with($sourceId, 'VOID:')) {
            return substr($sourceId, 5);
        }

        $parts = explode(':', $sourceId);

        return $parts[0] !== '' ? $parts[0] : $sourceId;
    }

    private function resolveSaleReference(string $saleId): ?string
    {
        $refs = $this->fetchSaleReferences([$saleId]);

        return $refs[$saleId] ?? null;
    }

    private function resolvePurchaseReference(string $purchaseId): ?string
    {
        $refs = $this->fetchPurchaseReferences([$purchaseId]);

        return $refs[$purchaseId] ?? null;
    }

    private function resolveTaxDocumentReference(string $taxDocumentId): ?string
    {
        $refs = $this->fetchTaxDocumentReferences([$taxDocumentId]);

        return $refs[$taxDocumentId] ?? null;
    }

    private function resolveStockEntryReference(string $entryId): ?string
    {
        $refs = $this->fetchStockEntryReferences([$entryId]);

        return $refs[$entryId] ?? null;
    }

    private function resolveVoidReference(string $sourceId): string
    {
        $movementId = str_starts_with($sourceId, 'VOID:') ? substr($sourceId, 5) : $sourceId;
        $row = $this->connection->fetchAssociative(<<<SQL
            SELECT sm.source_type, sm.source_id
            FROM stock_movements sm
            WHERE sm.id = ?
            SQL, [$movementId]);

        if ($row === false) {
            return 'Anulación';
        }

        $original = $this->resolve(
            $row['source_type'] !== null ? (string) $row['source_type'] : null,
            $row['source_id'] !== null ? (string) $row['source_id'] : null,
        );

        return $original !== null ? 'Anul. ' . $original : 'Anulación';
    }

    /**
     * @param list<string> $saleIds
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function fetchSaleReferences(array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT
                s.id,
                s.legacy_movimiento_id,
                s.series AS sale_series,
                s.number AS sale_number,
                td.series AS tax_series,
                td.number AS tax_number
            FROM sales s
            LEFT JOIN tax_document td
                ON (td.sale_id = s.id OR td.movimiento_id = s.legacy_movimiento_id)
               AND td.reason_code IS NULL
            WHERE s.id IN ($placeholders)
               OR s.legacy_movimiento_id IN ($placeholders)
            ORDER BY td.created_at DESC
            SQL, array_merge($saleIds, $saleIds));

        $refs = [];
        foreach ($rows as $row) {
            $reference = $this->formatSeriesNumber(
                $row['tax_series'] ?? $row['sale_series'] ?? null,
                $row['tax_number'] ?? $row['sale_number'] ?? null,
            );
            if ($reference === null) {
                continue;
            }

            $saleId = (string) $row['id'];
            if (!isset($refs[$saleId])) {
                $refs[$saleId] = $reference;
            }

            $legacyId = $row['legacy_movimiento_id'] ?? null;
            if ($legacyId !== null && $legacyId !== '') {
                $legacyKey = (string) $legacyId;
                if (!isset($refs[$legacyKey])) {
                    $refs[$legacyKey] = $reference;
                }
            }
        }

        return $refs;
    }

    /**
     * @param list<string> $purchaseIds
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function fetchPurchaseReferences(array $purchaseIds): array
    {
        if ($purchaseIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($purchaseIds), '?'));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT id, supplier_doc_series, supplier_doc_number, receipt_no
            FROM goods_receipts
            WHERE id IN ($placeholders)
            SQL, $purchaseIds);

        $refs = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $reference = $this->formatSeriesNumber(
                $row['supplier_doc_series'] ?? null,
                $row['supplier_doc_number'] ?? null,
                isset($row['receipt_no']) ? (int) $row['receipt_no'] : null,
                'RC',
            );
            if ($reference !== null) {
                $refs[$id] = $reference;
            }
        }

        return $refs;
    }

    /**
     * @param list<string> $taxDocumentIds
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function fetchTaxDocumentReferences(array $taxDocumentIds): array
    {
        if ($taxDocumentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taxDocumentIds), '?'));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT tax_document_id, series, number, document_type
            FROM tax_document
            WHERE tax_document_id IN ($placeholders)
            SQL, $taxDocumentIds);

        $refs = [];
        foreach ($rows as $row) {
            $id = (string) $row['tax_document_id'];
            $reference = $this->formatSeriesNumber($row['series'] ?? null, $row['number'] ?? null);
            if ($reference !== null) {
                $refs[$id] = $reference;
            }
        }

        return $refs;
    }

    /**
     * @param list<string> $entryIds
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function fetchStockEntryReferences(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT id, type
            FROM stock_entries
            WHERE id IN ($placeholders)
            SQL, $entryIds);

        $refs = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $type = match ((string) ($row['type'] ?? '')) {
                'RECEIPT' => 'Ingreso',
                'ISSUE' => 'Salida',
                'TRANSFER' => 'Traslado',
                'ADJUSTMENT' => 'Ajuste',
                default => 'Movimiento',
            };
            $refs[$id] = $type;
        }

        return $refs;
    }

    private function formatSeriesNumber(
        ?string $series,
        ?string $number,
        ?int $internalNo = null,
        ?string $internalPrefix = null,
    ): ?string {
        $series = $series !== null ? trim($series) : '';
        $number = $number !== null ? trim($number) : '';

        if ($series !== '' && $number !== '') {
            return $series . '-' . $number;
        }

        if ($number !== '') {
            return $number;
        }

        return $this->formatInternalReference($internalPrefix, $internalNo);
    }

    private function formatInternalReference(?string $prefix, ?int $internalNo): ?string
    {
        if ($internalNo === null || $internalNo <= 0 || $prefix === null || $prefix === '') {
            return null;
        }

        return $prefix . '-' . str_pad((string) $internalNo, 6, '0', STR_PAD_LEFT);
    }

    private function fallbackLabel(?string $sourceType): ?string
    {
        if ($sourceType === null || $sourceType === '') {
            return null;
        }

        return match ($sourceType) {
            'RECIPE_CONSUMPTION' => 'Consumo receta',
            default => ucfirst(strtolower(str_replace('_', ' ', $sourceType))),
        };
    }
}
