<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Lets a business bring the Excel it already uses, without a CSV detour. */
#[IsGranted('ROLE_USER')]
#[Route('/api/v1/onboarding/spreadsheets')]
final class OnboardingSpreadsheetController extends AbstractController
{
    /** @var array<string, array{headers: list<string>, sample: list<string>}> */
    private const TEMPLATES = [
        'customers' => [
            'headers' => ['RUC/DNI', 'Nombre', 'Teléfono', 'Correo'],
            'sample' => ['20100100101', 'Andina Retail SAC', '+51987111001', 'compras@andinaretail.pe'],
        ],
        'receivables' => [
            'headers' => ['RUC/DNI', 'Nombre', 'Monto', 'Vencimiento', 'Número', 'Teléfono'],
            'sample' => ['20100100101', 'Andina Retail SAC', '716.30', '2026-09-30', 'F001-88', '+51987111001'],
        ],
        'payables' => [
            'headers' => ['RUC', 'Proveedor', 'Monto', 'Vencimiento', 'Número'],
            'sample' => ['20100070970', 'Mayorista Tecnológica del Perú SAC', '12838.40', '2026-09-30', '00004505'],
        ],
    ];

    #[Route('/template/{type}', name: 'onboarding_spreadsheet_template', methods: ['GET'])]
    public function template(string $type): StreamedResponse
    {
        $definition = self::TEMPLATES[$type] ?? null;
        if ($definition === null) {
            throw new BadRequestHttpException('Plantilla no válida.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($definition['headers'], null, 'A1');
        $sheet->fromArray($definition['sample'], null, 'A2');
        $sheet
            ->getStyle('A1:' . $sheet->getHighestColumn() . '1')
            ->getFont()
            ->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $response = new StreamedResponse(static function () use ($spreadsheet): void {
            new Xlsx($spreadsheet)->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="plantilla-%s.xlsx"', $type));

        return $response;
    }

    #[Route('/rows', name: 'onboarding_spreadsheet_rows', methods: ['POST'])]
    public function rows(Request $request): JsonResponse
    {
        /** @var ?\Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $request->files->get('file');
        if ($file === null || strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            throw new BadRequestHttpException('Selecciona un archivo Excel (.xlsx).');
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $allRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable) {
            throw new BadRequestHttpException(
                'No se pudo leer el archivo Excel. Descarga la plantilla y vuelve a intentarlo.',
            );
        }

        $headers = array_map([$this, 'normaliseHeader'], array_shift($allRows) ?? []);
        if ($headers === [] || !array_filter($headers)) {
            throw new BadRequestHttpException('El Excel debe tener títulos en la primera fila.');
        }

        $rows = [];
        foreach ($allRows as $values) {
            if (!array_filter($values, static fn(mixed $value): bool => trim((string) $value) !== '')) {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$index] ?? ''));
                }
            }
            $rows[] = $row;
        }

        return $this->json(['rows' => $rows]);
    }

    private function normaliseHeader(mixed $header): string
    {
        $header = mb_strtolower(trim((string) $header));
        $header = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $header);

        return preg_replace('/\s+/', ' ', $header) ?? '';
    }
}
