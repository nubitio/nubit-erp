<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Repository\SunatRejectionQueryInterface;
use DateTimeInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sunat_status',
    description: 'Returns a summary of recent SUNAT rejection errors grouped by error code, plus the N most recent rejected documents.',
    method: 'sunatStatus',
)]
final readonly class SunatStatusTool
{
    /**
     * Maps well-known SUNAT response codes to human-readable Spanish labels.
     */
    private const array CODE_LABELS = [
        '0100' => 'Error de validación de datos',
        '0109' => 'El número de RUC no existe en SUNAT',
        '0111' => 'El RUC del emisor no está activo',
        '0112' => 'El RUC del emisor está de baja',
        '0156' => 'El comprobante ya fue registrado anteriormente',
        '0160' => 'El número de serie no corresponde al tipo de comprobante',
        '2075' => 'El monto del IGV no corresponde al calculado',
        '2800' => 'Error en la firma digital del comprobante',
        '2801' => 'Certificado digital vencido o no válido',
        '3000' => 'El comprobante fue rechazado por SUNAT',
        '3001' => 'El comprobante fue anulado',
    ];

    public function __construct(
        private SunatRejectionQueryInterface $rejectionQuery,
    ) {}

    /**
     * @param int $limit Maximum number of recent rejected documents to return (1–50).
     *
     * @return array{
     *     summary: list<array{code: string, label: string, count: int}>,
     *     recent_rejections: list<array{series: string, correlative: string, document_type: string, response_code: string, response_description: string|null, issued_at: string}>
     * }
     */
    public function sunatStatus(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $events = $this->rejectionQuery->findRejections();

        if ($events === []) {
            return [
                'summary' => [],
                'recent_rejections' => [],
            ];
        }

        // Build summary: group by code, count occurrences, take top 5
        /** @var array<string, int> $codeCounts */
        $codeCounts = [];
        foreach ($events as $event) {
            $code = $event->getCode() ?? 'UNKNOWN';
            $codeCounts[$code] = ($codeCounts[$code] ?? 0) + 1;
        }

        arsort($codeCounts);
        $top5 = array_slice($codeCounts, 0, 5, true);

        /** @var list<array{code: string, label: string, count: int}> $summary */
        $summary = [];
        foreach ($top5 as $code => $count) {
            $codeStr = (string) $code;
            $summary[] = [
                'code' => $codeStr,
                'label' => $this->resolveCodeLabel($codeStr),
                'count' => $count,
            ];
        }

        // Build recent rejections list (up to $limit)
        $recent = array_slice($events, 0, $limit);

        /** @var list<array{series: string, correlative: string, document_type: string, response_code: string, response_description: string|null, issued_at: string}> $recentRejections */
        $recentRejections = [];
        foreach ($recent as $event) {
            $doc = $event->getTaxDocument();
            $recentRejections[] = [
                'series' => $doc->getSeries(),
                'correlative' => $doc->getNumber(),
                'document_type' => $doc->getDocumentType(),
                'response_code' => $event->getCode() ?? 'UNKNOWN',
                'response_description' => $event->getMessage(),
                'issued_at' => $doc->getIssueDate()->format(DateTimeInterface::ATOM),
            ];
        }

        return [
            'summary' => $summary,
            'recent_rejections' => $recentRejections,
        ];
    }

    private function resolveCodeLabel(string $code): string
    {
        return self::CODE_LABELS[$code] ?? sprintf('Error SUNAT código %s', $code);
    }
}
