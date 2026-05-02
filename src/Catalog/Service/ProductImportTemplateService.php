<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates a downloadable Excel import template for bulk product upload.
 *
 * Layout:
 *  - Sheet "Productos": main sheet with headers, data validation and a sample row.
 *  - Sheet "_refs":     hidden sheet with dynamic DB lists used by data validation dropdowns.
 */
final readonly class ProductImportTemplateService
{
    /** Column letter → [header label, is_required] */
    private const array COLUMNS = [
        'A' => ['Código',                          false],
        'B' => ['Nombre',                          true],
        'C' => ['Categoría',                       true],
        'D' => ['Unidad de medida',                true],
        'E' => ['Precio de venta',                 true],
        'F' => ['Precio de compra',                false],
        'G' => ['SKU',                             false],
        'H' => ['Código de barras',                false],
        'I' => ['Tipo de producto',                false],
        'J' => ['Comportamiento de inventario',    false],
        'K' => ['Stock mínimo',                    false],
    ];

    /** Fixed enum values (Spanish label → raw value stored in DB) */
    private const array PRODUCT_KIND_OPTIONS = ['Bien', 'Servicio', 'Digital'];
    private const array INVENTORY_BEHAVIOR_OPTIONS = [
        'Inventario controlado',
        'Sin control de inventario',
        'Compuesto',
    ];

    public function __construct(
        private CategoryRepository $categoryRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
    ) {
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Plantilla importación de productos')
            ->setCreator('eFact');

        $mainSheet  = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('Productos');

        $refsSheet = $spreadsheet->createSheet();
        $refsSheet->setTitle('_refs');

        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);
        $units       = $this->unitOfMeasureRepository->findBy([], ['name' => 'ASC']);

        $this->buildRefsSheet($refsSheet, $categories, $units);
        $this->buildMainSheet($mainSheet, count($categories), count($units));

        // Hide the _refs sheet so it does not distract end-users.
        $refsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // Focus the main sheet on open.
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

    /** @param Category[]      $categories
     *  @param UnitOfMeasure[] $units */
    private function buildRefsSheet(Worksheet $sheet, array $categories, array $units): void
    {
        // Column A: category names
        foreach ($categories as $i => $cat) {
            $sheet->setCellValue('A' . ($i + 1), $cat->getName());
        }

        // Column B: unit of measure names
        foreach ($units as $i => $unit) {
            $sheet->setCellValue('B' . ($i + 1), $unit->getName());
        }

        // Column C: productKind options (Spanish)
        foreach (self::PRODUCT_KIND_OPTIONS as $i => $label) {
            $sheet->setCellValue('C' . ($i + 1), $label);
        }

        // Column D: inventoryBehavior options (Spanish)
        foreach (self::INVENTORY_BEHAVIOR_OPTIONS as $i => $label) {
            $sheet->setCellValue('D' . ($i + 1), $label);
        }
    }

    private function buildMainSheet(Worksheet $sheet, int $categoryCount, int $unitCount): void
    {
        // ---- Header row ----
        foreach (self::COLUMNS as $col => [$label, $required]) {
            $cell = $col . '1';
            $text = $required ? $label . ' *' : $label;
            $sheet->setCellValue($cell, $text);

            $bgColor = $required ? 'C0392B' : '7F8C8D'; // red vs grey
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            ]);
        }

        $sheet->getRowDimension(1)->setRowHeight(36);

        // ---- Sample row (row 2) ----
        $sheet->fromArray([
            'PROD-001',
            'Producto de ejemplo',
            '',  // category: filled via dropdown
            '',  // unit:     filled via dropdown
            '10.00',
            '7.50',
            'SKU-001',
            '1234567890123',
            'Bien',
            'Inventario controlado',
            '5',
        ], null, 'A2');

        $sheet->getStyle('A2:K2')->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECF0F1']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            'font'    => ['color' => ['argb' => 'FF7F8C8D'], 'italic' => true],
        ]);

        // ---- Column widths ----
        $widths = ['A' => 16, 'B' => 36, 'C' => 24, 'D' => 22, 'E' => 16,
                   'F' => 16, 'G' => 16, 'H' => 20, 'I' => 18, 'J' => 30, 'K' => 14];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ---- Freeze the header row ----
        $sheet->freezePane('A2');

        // ---- Data validation (rows 2 to 10001 = up to 10k rows) ----
        $first = 2;
        $last  = 10001;

        // Category dropdown → _refs!$A$1:$A$<n>
        if ($categoryCount > 0) {
            $this->addListValidation($sheet, "C{$first}:C{$last}", "_refs!\$A\$1:\$A\${$categoryCount}");
        }

        // Unit of measure dropdown → _refs!$B$1:$B$<n>
        if ($unitCount > 0) {
            $this->addListValidation($sheet, "D{$first}:D{$last}", "_refs!\$B\$1:\$B\${$unitCount}");
        }

        // productKind dropdown → _refs!$C$1:$C$3
        $this->addListValidation($sheet, "I{$first}:I{$last}", '_refs!$C$1:$C$' . count(self::PRODUCT_KIND_OPTIONS));

        // inventoryBehavior dropdown → _refs!$D$1:$D$3
        $this->addListValidation($sheet, "J{$first}:J{$last}", '_refs!$D$1:$D$' . count(self::INVENTORY_BEHAVIOR_OPTIONS));

        // ---- Instructions note in M1 ----
        $sheet->setCellValue('M1', '* Campos obligatorios  |  Fila 2 es de ejemplo, bórrela antes de importar');
        $sheet->getStyle('M1')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['argb' => 'FF7F8C8D'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getColumnDimension('M')->setWidth(60);
    }

    private function addListValidation(Worksheet $sheet, string $range, string $formula): void
    {
        // NOTE: getDataValidation() only accepts single cell coords in PhpSpreadsheet 5.x.
        // Build the object directly and register it via setDataValidation().
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(false); // false = show dropdown arrow in Excel
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valor no válido');
        $validation->setError('Por favor selecciona un valor de la lista desplegable.');
        $validation->setFormula1($formula);

        $sheet->setDataValidation($range, $validation);
    }
}
