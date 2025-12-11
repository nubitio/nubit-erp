<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Core\Color\ColorUtils;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class XlsExporter
{
    public function __construct(private SettingsService $settingsService)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $data
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function export(array $data, string $filename): StreamedResponse
    {
        $spreadsheet = $this->makeSpreadsheet($data);

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer): void {
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s.xlsx"', $filename));

        return $response;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function save(array $data, string $filename): void
    {
        $spreadsheet = $this->makeSpreadsheet($data);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filename . '.xlsx');
    }

    /**
     * @param array<int, array<string, mixed>> $data
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function makeSpreadsheet(array $data): Spreadsheet
    {
        $accentColor = $this->settingsService->get('app_theme_accent_color');
        $arr = explode(' ', strval($accentColor));
        $accentColor = dechex((int) $arr[0]) . dechex((int) $arr[1]) . dechex((int) $arr[2]);
        $lightenAccentColor = ColorUtils::lightenColor($accentColor, 60);
        $lightenAccentColor = str_replace('#', '', $lightenAccentColor);

        $contrastColor = ColorUtils::contrastColor($accentColor);
        $contrastColor = str_replace('#', '', $contrastColor);

        $headers = [];
        if (isset($data[0])) {
            $properties = array_keys($data[0]);
            foreach ($properties as $property) {
                $headers[$property] = ucfirst($property);
            }

            array_unshift($data, $headers);
        }

        $columnLetters = range('A', 'Z');
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => [
                    'rgb' => $contrastColor,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'FF' . $accentColor,
                ],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => [
                        'argb' => 'FF' . $accentColor,
                    ],
                ],
            ],
        ];

        $zebraStyle = new Conditional();
        $zebraStyle->setConditionType(Conditional::CONDITION_EXPRESSION);
        $zebraStyle->setOperatorType(Conditional::OPERATOR_EQUAL);
        $zebraStyle->addCondition('MOD(ROW(),2)=0');
        $zebraStyle->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $zebraStyle->getStyle()->getFill()->getStartColor()->setARGB('FF' . $lightenAccentColor);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Efact')
            ->setTitle('Efact export');

        $spreadsheet->setActiveSheetIndex(0);
        if ($data !== []) {
            $spreadsheet->getActiveSheet()->fromArray($data);
        } else {
            $spreadsheet->getActiveSheet()->setCellValue('A1', 'No data');
        }

        $lastColumn = [] === $headers ? 'A' : $columnLetters[count($headers) - 1];
        foreach (range('A', $lastColumn) as $columnID) {
            $spreadsheet->getActiveSheet()->getColumnDimension($columnID)
                ->setAutoSize(true);
        }

        $spreadsheet->getActiveSheet()->getStyle('A1:' . $lastColumn . '1')
            ->applyFromArray($headerStyle);

        $spreadsheet->getActiveSheet()->getStyle('A2:' . $lastColumn . count($data))
            ->setConditionalStyles([$zebraStyle]);

        $spreadsheet->getActiveSheet()->getStyle('A2:' . $lastColumn . count($data))
            ->applyFromArray($borderStyle);

        return $spreadsheet;
    }
}
