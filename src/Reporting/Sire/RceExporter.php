<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use App\Organization\Service\CompanyContext;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Genera el archivo «Reemplazar propuesta» del RCE (SIRE compras) a partir de
 * las recepciones de mercadería contabilizadas del periodo, comprimido en el
 * ZIP que exige SUNAT.
 *
 * Limitaciones conocidas, que se informan y no se corrigen:
 *  - La fecha de emisión es la de la recepción: la recepción no guarda la del
 *    comprobante del proveedor.
 *  - Las compras sin comprobante del proveedor y las notas se excluyen.
 *  - Las facturas de proveedor de Cuentas por Pagar (servicios y gastos) no
 *    entran: no guardan base imponible ni IGV.
 */
final readonly class RceExporter
{
    public const string BOOK_CODE = '080400';

    /** Oportunidad 02: reemplaza la propuesta. */
    private const string OPPORTUNITY = '02';

    /** Banderas: 1 operativa, 1 con información, 1 soles, 2 generado por el SIRE. */
    private const string FLAGS = '1112';

    public function __construct(
        private Connection $connection,
        private CompanyContext $companyContext,
        private RceRowMapper $mapper = new RceRowMapper(),
        private RceReviewer $reviewer = new RceReviewer(),
    ) {}

    public function export(string $period): SireExportResult
    {
        $builder = new RceRecordBuilder($this->companyContext->getId(), $this->companyContext->getName(), $period);

        $content = '';
        $count = 0;
        $excluded = [];
        $warnings = [];

        foreach ($this->fetchRows($period) as $data) {
            $row = new SireRow($data);
            $label = sprintf(
                '%s-%s %s',
                $row->text('supplier_doc_series'),
                $row->text('supplier_doc_number'),
                $row->text('supplier_doc_type'),
            );

            $reason = $this->reviewer->exclusionReason($row);
            if ($reason !== null) {
                $excluded[] = ['document' => trim($label, '- '), 'reason' => $reason];

                continue;
            }

            $document = $this->mapper->toDocument($row);
            foreach ($this->reviewer->review($document) as $warning) {
                $warnings[] = sprintf('%s: %s', $label, $warning);
            }

            $content .= $builder->build($document) . SireTextFormat::NEWLINE;
            ++$count;
        }

        $txtName = $this->fileName($period, '.TXT');
        $zipName = $this->fileName($period, '.zip');

        return new SireExportResult(
            txtName: $txtName,
            zipName: $zipName,
            content: $content,
            zip: $count > 0 ? SireZip::create($txtName, $content) : '',
            recordCount: $count,
            excluded: $excluded,
            warnings: $warnings,
        );
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

        $sql = <<<'SQL'
                SELECT
                    gr.supplier_doc_type, gr.supplier_doc_series, gr.supplier_doc_number, gr.issued_at,
                    gr.subtotal, gr.tax_total, gr.grand_total, gr.currency, gr.exchange_rate,
                    p.ruc AS supplier_doc, p.razon_social AS supplier_name
                FROM goods_receipts gr
                LEFT JOIN proveedor p ON p.proveedor_id = gr.supplier_id
                WHERE gr.issued_at BETWEEN ? AND ?
                  AND gr.deleted_at IS NULL
                  AND gr.status = 'POSTED'
                ORDER BY gr.issued_at, gr.supplier_doc_series, gr.supplier_doc_number
            SQL;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, [
            $start->format('Y-m-d'),
            $end->format('Y-m-d') . ' 23:59:59',
        ]);

        return $rows;
    }
}
