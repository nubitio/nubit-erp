<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsExporter
{
    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @throws Exception
     */
    public function export(array $data, string $filename): StreamedResponse
    {
        $accentColor = $this->settingsService->get('app_theme_accent_color');
        $arr = explode(' ', $accentColor);
        $accentColor = dechex($arr[0]) . dechex($arr[1]) . dechex($arr[2]);

        $contrastColor = $this->settingsService->get('app_theme_contrast_color');
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

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Efact')
            ->setTitle('Efact export');

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->fromArray($data);

        foreach (range('A', $columnLetters[count($headers) - 1]) as $columnID) {
            $spreadsheet->getActiveSheet()->getColumnDimension($columnID)
                ->setAutoSize(true);
        }

        $spreadsheet->getActiveSheet()->getStyle('A1:' . $columnLetters[count($headers) - 1] . '1')
            ->applyFromArray($headerStyle);

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s.xlsx"', $filename));

        return $response;
    }
}