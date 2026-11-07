<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use App\Organization\Service\CompanyContext;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;
use ZipArchive;

/**
 * Genera el archivo «Reemplazar propuesta» del RVIE (SIRE ventas) a partir de
 * los comprobantes emitidos del periodo, comprimido en el ZIP que exige SUNAT.
 *
 * Reglas aplicadas (ver {@see RvieRecordBuilder}):
 *  - Van factura (01), boleta (03), nota de crédito (07) y nota de débito (08).
 *  - Los comprobantes dados de baja se EXCLUYEN y se informan aparte: SUNAT los
 *    conoce por su propia vía y no deben venir como venta.
 *  - Las bases se toman de las líneas por tipo de afectación (Catálogo 07):
 *    10 gravado, 20 exonerado, 30 inafecto, 40 exportación. Sin líneas, la base
 *    del documento se informa como gravada.
 *  - No se corrige nada en silencio: lo que no cuadra o falta se devuelve como
 *    advertencia para que se revise antes de presentar.
 */
final readonly class RvieExporter
{
    public const string BOOK_CODE = '140400';

    /** Oportunidad 02: reemplaza la propuesta. */
    private const string OPPORTUNITY = '02';

    /** Banderas: 1 operativa, 1 con información, 1 soles, 2 generado por el SIRE. */
    private const string FLAGS = '1112';

    private const array DOC_TYPES = ['01', '03', '07', '08'];

    public function __construct(
        private Connection $connection,
        private CompanyContext $companyContext,
        private RvieRowMapper $mapper = new RvieRowMapper(),
        private RvieReviewer $reviewer = new RvieReviewer(),
    ) {}

    /**
     * @return array{
     *     txtName: string, zipName: string, content: string, zip: string,
     *     recordCount: int, excluded: list<array<string, string>>, warnings: list<string>
     * }
     */
    public function export(string $period): array
    {
        $rows = $this->fetchRows($period);
        $builder = new RvieRecordBuilder($this->companyContext->getId(), $this->companyContext->getName(), $period);

        $content = '';
        $count = 0;
        $excluded = [];
        $warnings = [];

        foreach ($rows as $data) {
            $row = new RvieRow($data);
            $label = sprintf('%s-%s %s', $row->text('series'), $row->text('number'), $row->text('document_type'));
            if (!$row->isNull('void_ticket') || !$row->isNull('void_correlation')) {
                $excluded[] = ['document' => $label, 'reason' => 'Dado de baja'];

                continue;
            }

            $document = $this->mapper->toDocument($row);
            foreach ($this->reviewer->review($document, $this->mapper->hasLines($row)) as $warning) {
                $warnings[] = sprintf('%s: %s', $label, $warning);
            }

            $content .= $builder->build($document) . SireTextFormat::NEWLINE;
            ++$count;
        }

        $txtName = $this->fileName($period, '.TXT');
        $zipName = $this->fileName($period, '.zip');

        return [
            'txtName' => $txtName,
            'zipName' => $zipName,
            'content' => $content,
            'zip' => $count > 0 ? $this->zip($txtName, $content) : '',
            'recordCount' => $count,
            'excluded' => $excluded,
            'warnings' => $warnings,
        ];
    }

    private function fileName(string $period, string $extension): string
    {
        return SireTextFormat::bookFileName(
            $this->companyContext->getId(),
            $period,
            self::BOOK_CODE,
            self::OPPORTUNITY,
            self::FLAGS,
            $extension,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $period): array
    {
        $start = DateTimeImmutable::createFromFormat('!Ymd', $period . '01');
        if ($start === false) {
            throw new RuntimeException('Periodo inválido, se espera AAAAMM.');
        }
        $end = $start->modify('last day of this month');

        $placeholders = implode(',', array_fill(0, count(self::DOC_TYPES), '?'));
        $sql = <<<SQL
                SELECT
                    td.document_type, td.series, td.number, td.issue_date,
                    td.taxable_amount, td.igv_amount, td.total, td.currency, td.exchange_rate,
                    td.void_ticket, td.void_correlation,
                    c.ruc AS customer_doc, c.razon_social AS customer_name,
                    rel.document_type AS modified_doc_type, rel.series AS modified_series,
                    rel.number AS modified_number, rel.issue_date AS modified_issue_date,
                    COALESCE(l.line_count, 0) AS line_count,
                    COALESCE(l.taxable, 0) AS line_taxable,
                    COALESCE(l.exempt, 0) AS line_exempt,
                    COALESCE(l.unaffected, 0) AS line_unaffected,
                    COALESCE(l.export, 0) AS line_export
                FROM tax_document td
                LEFT JOIN cliente c ON c.id = td.customer_id
                LEFT JOIN tax_document rel ON rel.tax_document_id = td.related_tax_document_id
                LEFT JOIN (
                    SELECT tax_document_id,
                           COUNT(*) AS line_count,
                           SUM(CASE WHEN tax_type = '10' THEN COALESCE(taxed_amount, 0) ELSE 0 END) AS taxable,
                           SUM(CASE WHEN tax_type = '20' THEN COALESCE(taxed_amount, 0) ELSE 0 END) AS exempt,
                           SUM(CASE WHEN tax_type = '30' THEN COALESCE(taxed_amount, 0) ELSE 0 END) AS unaffected,
                           SUM(CASE WHEN tax_type = '40' THEN COALESCE(taxed_amount, 0) ELSE 0 END) AS export
                    FROM tax_document_line
                    GROUP BY tax_document_id
                ) l ON l.tax_document_id = td.tax_document_id
                WHERE td.issue_date BETWEEN ? AND ?
                  AND td.document_type IN ($placeholders)
                ORDER BY td.issue_date, td.series, CAST(td.number AS UNSIGNED)
            SQL;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            ...self::DOC_TYPES,
        ]);

        return $rows;
    }

    private function zip(string $entryName, string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rvie_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del ZIP.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo abrir el archivo ZIP.');
            }
            $zip->addFromString($entryName, $content);
            $zip->close();

            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new RuntimeException('No se pudo leer el archivo ZIP generado.');
            }

            return $bytes;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
