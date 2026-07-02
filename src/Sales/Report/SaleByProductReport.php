<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.sale_report')]
final class SaleByProductReport extends AbstractSaleReport
{
    public function key(): string
    {
        return 'sale-by-product';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate' => 's.issued_date',
            'status' => 's.status',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
            select
                coalesce(p.codigo, '-') as product_code,
                coalesce(p.descripcion, 'Sin producto') as product_name,
                c.descripcion as category,
                sum(si.qty) as quantity,
                round(sum(si.net_amount) / nullif(sum(si.qty),0), 2) as average_unit_price,
                sum(si.net_amount) as total
            from sales s
                inner join sale_items si on si.sale_id = s.id
                left join producto p on p.producto_id = si.product_id
                left join categoria c on c.categoria_id = p.categoria_id
            where
                s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
                and s.issued_date between :date_from and :date_to
                and s.branch_id = :local_id
                {$receiptFilter}
                {$gridFilter}
            group by product_code, product_name, category
            order by total desc
            SQL;
    }

    public function columns(): array
    {
        return [
            'product_code' => 'Código',
            'product_name' => 'Producto',
            'category' => 'Categoría',
            'quantity' => 'Cantidad',
            'average_unit_price' => 'Precio unit. prom.',
            'total' => 'Total',
        ];
    }

    public function filename(): string
    {
        return 'reporte_ventas_por_producto';
    }
}
