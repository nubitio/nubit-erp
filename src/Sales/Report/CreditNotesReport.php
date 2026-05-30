<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Nubit\Platform\Report\ExportableReportInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Credit-notes report queries tax_document directly,
 * so it does not need the receipt filter.
 */
#[AutoconfigureTag('app.sale_report')]
final class CreditNotesReport implements ExportableReportInterface
{
    public function key(): string
    {
        return 'credit-notes';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate'        => 'n.issue_date',
            'customer'          => 'c.id',
            'invoiceFullNumber' => "concat(n.series, '-', n.number)",
            'grandTotal'        => 'n.total',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        return <<<SQL
select
    n.issue_date as issue_date,
    concat(n.series, '-', n.number) as document,
    n.document_type as note_type,
    n.reason_code as reason_code,
    n.reason_description as reason,
    n.affected_document as affected_document,
    n.total,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc
from tax_document n
    left join cliente c on c.id = n.customer_id
where
    n.document_type in ('07', '08')
    and n.issue_date between :date_from and :date_to
    and n.branch_id = :local_id
    {$gridFilter}
order by n.issue_date desc
SQL;
    }

    public function columns(): array
    {
        return [
            'issue_date'        => 'Fecha emisión',
            'document'          => 'Comprobante',
            'note_type'         => 'Tipo nota',
            'reason_code'       => 'Código motivo',
            'reason'            => 'Motivo',
            'affected_document' => 'Doc. afectado',
            'total'             => 'Total',
            'customer_name'     => 'Cliente',
            'customer_ruc'      => 'RUC/DNI',
        ];
    }

    public function filename(): string
    {
        return 'reporte_notas_de_credito';
    }
}
