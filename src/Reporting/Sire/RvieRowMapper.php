<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use DateTimeImmutable;

/**
 * Convierte una fila de `tax_document` (con el resumen de sus líneas y el
 * comprobante que modifica) en un {@see RvieDocument}.
 *
 * Las bases salen de las líneas por tipo de afectación (Catálogo 07):
 * 10 gravado, 20 exonerado, 30 inafecto, 40 exportación. Sin líneas con
 * importe, la base del documento se informa como gravada.
 */
final readonly class RvieRowMapper
{
    public function toDocument(RvieRow $row): RvieDocument
    {
        return new RvieDocument(
            type: $row->text('document_type'),
            series: $row->text('series'),
            number: $row->text('number'),
            issueDate: new DateTimeImmutable($row->text('issue_date')),
            customer: RvieCustomer::fromStored($row->nullable('customer_doc'), $row->nullable('customer_name')),
            amounts: $this->amounts($row),
            currency: $row->text('currency') ?: 'PEN',
            exchangeRate: $row->nullable('exchange_rate'),
            modified: $this->modified($row),
        );
    }

    public function hasLines(RvieRow $row): bool
    {
        return $row->int('line_count') > 0;
    }

    private function amounts(RvieRow $row): RvieAmounts
    {
        $lines = RvieAmounts::of(
            total: (string) $row->money('total'),
            taxable: (string) $row->money('line_taxable'),
            exempt: (string) $row->money('line_exempt'),
            unaffected: (string) $row->money('line_unaffected'),
            export: (string) $row->money('line_export'),
        );

        if ($this->hasLines($row) && $lines->componentsSum()->isPositive()) {
            return new RvieAmounts(
                $lines->export,
                $lines->taxable,
                $row->money('igv_amount'),
                $lines->exempt,
                $lines->unaffected,
                $lines->total,
            );
        }

        return RvieAmounts::of(
            total: (string) $row->money('total'),
            taxable: (string) $row->money('taxable_amount'),
            igv: (string) $row->money('igv_amount'),
        );
    }

    private function modified(RvieRow $row): ?RvieModifiedDocument
    {
        $type = $row->nullable('modified_doc_type');
        $number = $row->nullable('modified_number');
        $date = $row->nullable('modified_issue_date');
        if ($type === null || $number === null || $date === null) {
            return null;
        }

        return new RvieModifiedDocument(new DateTimeImmutable($date), $type, $row->text('modified_series'), $number);
    }
}
