<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a downloadable .xlsx file containing all current products.
 *
 * The output uses the same column layout as the import template so users can
 * edit and re-import without reformatting.  Relation fields (category, unit)
 * are written as human-readable names; enum fields use the same Spanish labels
 * the template dropdowns display.
 */
final readonly class ProductExportService
{
    /** Enum → Spanish label maps (inverse of ProductImportProcessorService) */
    private const array PRODUCT_KIND_LABELS = [
        'GOOD'    => 'Bien',
        'SERVICE' => 'Servicio',
        'DIGITAL' => 'Digital',
    ];

    private const array INVENTORY_BEHAVIOR_LABELS = [
        'TRACKED'     => 'Inventario controlado',
        'NOT_TRACKED' => 'Sin control de inventario',
        'COMPOSITE'   => 'Compuesto',
    ];

    private const array PRODUCT_KIND_OPTIONS = ['Bien', 'Servicio', 'Digital'];
    private const array INVENTORY_BEHAVIOR_OPTIONS = [
        'Inventario controlado',
        'Sin control de inventario',
        'Compuesto',
    ];

    private const array HEADERS = [
        'A' => ['Código',                       false],
        'B' => ['Nombre',                        true],
        'C' => ['Categoría',                     true],
        'D' => ['Unidad de medida',              true],
        'E' => ['Precio de venta',               true],
        'F' => ['Precio de compra',              false],
        'G' => ['SKU',                           false],
        'H' => ['Código de barras',              false],
        'I' => ['Tipo de producto',              false],
        'J' => ['Comportamiento de inventario',  false],
        'K' => ['Stock mínimo',                  false],
    ];

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
    ) {
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Exportación de productos')
            ->setCreator('eFact');

        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('Productos');

        $refsSheet = $spreadsheet->createSheet();
        $refsSheet->setTitle('_refs');
        $refsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);
        $units       = $this->unitOfMeasureRepository->findBy([], ['name' => 'ASC']);

        $this->buildRefsSheet($refsSheet, count($categories), count($units));
        $this->populateRefsData($refsSheet, $categories, $units);
        $this->buildHeaders($mainSheet);
        $this->addValidations($mainSheet, count($categories), count($units));
        $this->populateProducts($mainSheet);

        $mainSheet->freezePane('A2');
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function stream(Spreadsheet $spreadsheet): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @param Category[] $categories
     *  @param UnitOfMeasure[] $units */
    private function populateRefsData(Worksheet $sheet, array $categories, array $units): void
    {
        foreach ($categories as $i => $cat) {
            $sheet->setCellValue('A' . ($i + 1), $cat->getName());
        }
        foreach ($units as $i => $unit) {
            $sheet->setCellValue('B' . ($i + 1), $unit->getName());
        }
        foreach (self::PRODUCT_KIND_OPTIONS as $i => $label) {
            $sheet->setCellValue('C' . ($i + 1), $label);
        }
        foreach (self::INVENTORY_BEHAVIOR_OPTIONS as $i => $label) {
            $sheet->setCellValue('D' . ($i + 1), $label);
        }
    }

    private function buildRefsSheet(Worksheet $sheet, int $catCount, int $unitCount): void
    {
        // Headers in row 1 (informational, hidden sheet)
        foreach (['A' => 'Categorías', 'B' => 'Unidades', 'C' => 'Tipo producto', 'D' => 'Comportamiento'] as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }
    }

    private function buildHeaders(Worksheet $mainSheet): void
    {
        foreach (self::HEADERS as $col => [$label, $required]) {
            $cell = $col . '1';
            $text = $required ? $label . ' *' : $label;
            $mainSheet->setCellValue($cell, $text);

            $bgColor = $required ? 'C0392B' : '7F8C8D';
            $mainSheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            ]);
        }

        $mainSheet->getRowDimension(1)->setRowHeight(36);

        $widths = ['A' => 16, 'B' => 36, 'C' => 24, 'D' => 22, 'E' => 16,
                   'F' => 16, 'G' => 16, 'H' => 20, 'I' => 18, 'J' => 30, 'K' => 14];
        foreach ($widths as $col => $width) {
            $mainSheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function addValidations(Worksheet $sheet, int $catCount, int $unitCount): void
    {
        $first = 2;
        $last  = 100001; // generous upper bound for exports

        if ($catCount > 0) {
            $this->addListValidation($sheet, "C{$first}:C{$last}", "_refs!\$A\$2:\$A\$" . ($catCount + 1));
        }
        if ($unitCount > 0) {
            $this->addListValidation($sheet, "D{$first}:D{$last}", "_refs!\$B\$2:\$B\$" . ($unitCount + 1));
        }
        $this->addListValidation($sheet, "I{$first}:I{$last}", '_refs!$C$2:$C$' . (count(self::PRODUCT_KIND_OPTIONS) + 1));
        $this->addListValidation($sheet, "J{$first}:J{$last}", '_refs!$D$2:$D$' . (count(self::INVENTORY_BEHAVIOR_OPTIONS) + 1));
    }

    private function populateProducts(Worksheet $sheet): void
    {
        /** @var Product[] $products */
        $products = $this->productRepository->findBy([], ['name' => 'ASC']);

        $row = 2;
        foreach ($products as $product) {
            $sheet->fromArray([
                $product->getCode() ?? '',
                $product->getName(),
                $product->getCategory()->getName(),
                $product->getUnitOfMeasure()->getName(),
                $product->getSalePrice(),
                $product->getPurchasePrice() ?? '',
                $product->getSku() ?? '',
                $product->getBarcode() ?? '',
                self::PRODUCT_KIND_LABELS[$product->getProductKind()] ?? $product->getProductKind(),
                self::INVENTORY_BEHAVIOR_LABELS[$product->getInventoryBehavior()] ?? $product->getInventoryBehavior(),
                $product->getMinStock() ?? '',
            ], null, 'A' . $row);

            // Stripe every other row for readability
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                ]);
            }

            $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);

            $row++;
        }
    }

    private function addListValidation(Worksheet $sheet, string $range, string $formula): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(false);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valor no válido');
        $validation->setError('Por favor selecciona un valor de la lista desplegable.');
        $validation->setFormula1($formula);

        $sheet->setDataValidation($range, $validation);
    }
}
