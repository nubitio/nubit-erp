<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Nubit\Platform\Export\XlsColumnSpec;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.sale_report')]
final class PaymentMethodDailyReport extends AbstractSaleReport
{
    public function key(): string
    {
        return 'payment-method-daily';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate' => 's.issued_date',
            'paymentMethod' => 'pm.id',
            'status' => 's.status',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
            select
                s.issued_date as sale_date,
                coalesce(pm.name, 'Sin método') as payment_method,
                count(distinct s.id) as transactions,
                sum(sp.amount) as total
            from sales s
                inner join sale_payments sp on sp.sale_id = s.id
                left join payment_method pm on pm.id = sp.payment_method_id
            where
                s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
                and s.issued_date between :date_from and :date_to
                and s.branch_id = :local_id
                {$receiptFilter}
                {$gridFilter}
            group by sale_date, payment_method
            order by sale_date desc, payment_method
            SQL;
    }

    public function columns(): array
    {
        return [
            'sale_date' => 'Fecha',
            'payment_method' => 'Método de pago',
            'transactions' => XlsColumnSpec::integer('Transacciones'),
            'total' => XlsColumnSpec::number('Total', '#,##0.00'),
        ];
    }

    public function filename(): string
    {
        return 'reporte_pagos_diarios';
    }
}
