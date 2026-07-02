<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Dto\ProductImportResult;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\ProductKind;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

/**
 * Reads an uploaded .xlsx file and upserts products row by row.
 *
 * Column mapping (1-indexed):
 *  1  A  Código               (code)              optional  — upsert key
 *  2  B  Nombre               (name)              REQUIRED
 *  3  C  Categoría            (category)          REQUIRED  — resolved by name
 *  4  D  Unidad de medida     (unitOfMeasure)     REQUIRED  — resolved by name
 *  5  E  Precio de venta      (salePrice)         REQUIRED
 *  6  F  Precio de compra     (purchasePrice)     optional
 *  7  G  SKU                  (sku)               optional
 *  8  H  Código de barras     (barcode)           optional
 *  9  I  Tipo de producto     (productKind)       optional  default GOOD
 * 10  J  Comportamiento inv.  (inventoryBehavior) optional  default TRACKED
 * 11  K  Stock mínimo         (minStock)          optional
 *
 * Behaviour:
 *  - Row 1 is the header row and is always skipped.
 *  - Completely empty rows are silently skipped.
 *  - Upsert logic: if `code` is provided and a product with that code exists → update;
 *    otherwise create a new product.
 *  - Invalid rows are collected in the result; processing continues (skip-and-continue).
 */
final readonly class ProductImportProcessorService
{
    /** Spanish → enum value maps */
    private const array PRODUCT_KIND_MAP = [
        'bien' => 'GOOD',
        'servicio' => 'SERVICE',
        'digital' => 'DIGITAL',
    ];

    private const array INVENTORY_BEHAVIOR_MAP = [
        'inventario controlado' => 'TRACKED',
        'sin control de inventario' => 'NOT_TRACKED',
        'compuesto' => 'COMPOSITE',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Process the file and return a result DTO.
     *
     * @param callable(int $current, int $total): void $onProgress  called after each processed row
     */
    public function process(string $filePath, callable $onProgress): ProductImportResult
    {
        $result = new ProductImportResult();

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $dataRows = $highestRow - 1; // exclude header row 1

        if ($dataRows <= 0) {
            return $result;
        }

        // Pre-load lookup maps (name → entity) for fast resolution.
        $categoryMap = $this->buildNameMap($this->categoryRepository->findAll());
        $unitMap = $this->buildNameMap($this->unitOfMeasureRepository->findAll());

        /** @var array<string, int> $seenNames  lowercase name → first row number */
        $seenNames = [];
        /** @var array<string, int> $seenCodes  code → first row number */
        $seenCodes = [];

        $batchSize = 50;
        $processed = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $this->readRow($sheet, $row);

            // Skip completely empty rows.
            if ($this->isEmptyRow($values)) {
                $onProgress(++$processed, $dataRows);
                continue;
            }

            try {
                $this->processRow($row, $values, $categoryMap, $unitMap, $result, $seenNames, $seenCodes);
            } catch (\Throwable $e) {
                $this->logger->warning('product_import.row_failed', [
                    'row' => $row,
                    'exception' => $e->getMessage(),
                ]);
                $result->addError($row, $e->getMessage());
            }

            $onProgress(++$processed, $dataRows);

            // Flush every N rows to avoid memory exhaustion on large imports.
            if (($processed % $batchSize) === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();
        $spreadsheet->disconnectWorksheets();

        return $result;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return list<string> */
    private function readRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): array
    {
        return [
            $this->cellStr($sheet, 'A', $row), // code
            $this->cellStr($sheet, 'B', $row), // name
            $this->cellStr($sheet, 'C', $row), // category
            $this->cellStr($sheet, 'D', $row), // unit of measure
            $this->cellStr($sheet, 'E', $row), // salePrice
            $this->cellStr($sheet, 'F', $row), // purchasePrice
            $this->cellStr($sheet, 'G', $row), // sku
            $this->cellStr($sheet, 'H', $row), // barcode
            $this->cellStr($sheet, 'I', $row), // productKind
            $this->cellStr($sheet, 'J', $row), // inventoryBehavior
            $this->cellStr($sheet, 'K', $row), // minStock
        ];
    }

    private function cellStr(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $col, int $row): string
    {
        return trim((string) ($sheet->getCell($col . $row)->getValue() ?? ''));
    }

    /** @param list<string> $values */
    private function isEmptyRow(array $values): bool
    {
        return array_filter($values, fn(string $v) => $v !== '') === [];
    }

    /**
     * @param array<string, Category|UnitOfMeasure> $categoryMap
     * @param array<string, UnitOfMeasure>           $unitMap
     * @param list<string>               $values
     * @param array<string, int>         $seenNames   tracks names seen in this file (mutated)
     * @param array<string, int>         $seenCodes   tracks codes seen in this file (mutated)
     */
    private function processRow(
        int $row,
        array $values,
        array $categoryMap,
        array $unitMap,
        ProductImportResult $result,
        array &$seenNames,
        array &$seenCodes,
    ): void {
        [
            $code,
            $name,
            $categoryName,
            $unitName,
            $salePrice,
            $purchasePrice,
            $sku,
            $barcode,
            $productKindRaw,
            $inventoryBehaviorRaw,
            $minStock,
        ] = $values;

        // ---- Validate required fields ----
        $errors = [];

        if ($name === '') {
            $errors[] = 'Nombre es obligatorio';
        }
        if ($categoryName === '') {
            $errors[] = 'Categoría es obligatoria';
        }
        if ($unitName === '') {
            $errors[] = 'Unidad de medida es obligatoria';
        }
        if ($salePrice === '' || !is_numeric($salePrice)) {
            $errors[] = 'Precio de venta debe ser un número válido';
        }

        if ($errors !== []) {
            $result->addError($row, implode('; ', $errors));

            return;
        }

        // ---- Intra-file duplicate detection ----
        $nameLower = mb_strtolower($name);
        if (isset($seenNames[$nameLower])) {
            $result->addError($row, "Nombre duplicado en este archivo (ya aparece en fila {$seenNames[$nameLower]})");

            return;
        }
        $seenNames[$nameLower] = $row;

        if ($code !== '' && isset($seenCodes[$code])) {
            $result->addError(
                $row,
                "Código '{$code}' duplicado en este archivo (ya aparece en fila {$seenCodes[$code]})",
            );

            return;
        }
        if ($code !== '') {
            $seenCodes[$code] = $row;
        }

        // ---- Resolve relations ----
        $category = $categoryMap[$this->normalizeLookupName($categoryName)] ?? null;
        if ($category === null) {
            $result->addError($row, "Categoría '{$categoryName}' no encontrada");

            return;
        }

        $unit = $unitMap[$this->normalizeLookupName($unitName)] ?? null;
        if ($unit === null) {
            $result->addError($row, "Unidad de medida '{$unitName}' no encontrada");

            return;
        }

        // ---- Resolve enums (with graceful fallback) ----
        $productKindValue = $productKindRaw !== ''
            ? self::PRODUCT_KIND_MAP[mb_strtolower($productKindRaw)] ?? null
            : null;
        if ($productKindValue === null && $productKindRaw !== '') {
            $result->addError($row, "Tipo de producto '{$productKindRaw}' no reconocido");

            return;
        }
        $productKind = ProductKind::from($productKindValue ?? 'GOOD');

        $inventoryBehaviorValue = $inventoryBehaviorRaw !== ''
            ? self::INVENTORY_BEHAVIOR_MAP[mb_strtolower($inventoryBehaviorRaw)] ?? null
            : null;
        if ($inventoryBehaviorValue === null && $inventoryBehaviorRaw !== '') {
            $result->addError($row, "Comportamiento de inventario '{$inventoryBehaviorRaw}' no reconocido");

            return;
        }
        $inventoryBehavior = InventoryBehavior::from($inventoryBehaviorValue ?? 'TRACKED');

        // ---- Upsert logic ----
        // Priority: code match → name match → create new.
        $isNew = false;
        $product = null;

        if ($code !== '') {
            // 1️⃣ Try to match by code (primary upsert key)
            $product = $this->productRepository->findOneBy(['code' => $code]);
        }

        if ($product === null) {
            // 2️⃣ Fallback: match by name to avoid creating duplicates
            $product = $this->productRepository->findOneBy(['name' => $name]);
        }

        if ($product === null) {
            // 3️⃣ No match — create new product
            $product = new Product();
            $isNew = true;
        }

        if ($code !== '') {
            $product->setCode($code);
        }
        $product->setName($name);
        $product->setCategory($category);
        $product->setUnitOfMeasure($unit);
        $product->setSalePrice($salePrice);
        $product->setProductKind($productKind);
        $product->setInventoryBehavior($inventoryBehavior);
        // Derive legacy `type` field from productKind
        $product->setType($productKind === ProductKind::GOOD ? 1 : 2);

        if ($purchasePrice !== '' && is_numeric($purchasePrice)) {
            $product->setPurchasePrice($purchasePrice);
        }
        if ($sku !== '') {
            $product->setSku($sku);
        }
        if ($barcode !== '') {
            $product->setBarcode($barcode);
        }
        if ($minStock !== '' && is_numeric($minStock)) {
            $product->setMinStock($minStock);
        }

        if ($isNew) {
            $this->em->persist($product);
            $result->created++;
        } else {
            $result->updated++;
        }
    }

    /**
     * Build a lowercase-name → entity map for fast lookups.
     *
     * @param array<Category|UnitOfMeasure> $entities
     * @return array<string, Category|UnitOfMeasure>
     */
    private function buildNameMap(array $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            $map[$this->normalizeLookupName($entity->getName())] = $entity;
        }

        return $map;
    }

    private function normalizeLookupName(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        return mb_strtolower($value);
    }
}
