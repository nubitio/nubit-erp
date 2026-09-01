<?php

declare(strict_types=1);

namespace App\Sales\Report;

use App\Settings\Service\SunatStateService;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Export\XlsColumnSpec;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One row per sale, one column per active payment method with the amount paid
 * via that method, plus "Total pagado" / "Total comprobante". The XLSX exporter
 * adds a footer row that sums every column — i.e. the per-method day totals.
 *
 * Method columns are dynamic: `pm_<id>` aliases are built from the tenant's
 * active payment methods, and `columns()` derives the same list so the aliases
 * line up. Method ids are integers straight from the DB, so they are
 * interpolated into the SQL the same way `receiptFilter`/`gridFilter` already are.
 */
#[AutoconfigureTag('app.sale_report')]
final class PaymentMethodBreakdownReport extends AbstractSaleReport
{
    /** @var list<array{id: int, name: string}>|null */
    private ?array $methodsCache = null;

    public function __construct(
        SunatStateService $sunatState,
        private readonly Connection $connection,
    ) {
        parent::__construct($sunatState);
    }

    public function key(): string
    {
        return 'payment-method-breakdown';
    }

    public function fieldMap(): array
    {
        // No `paymentMethod` — filtering payment rows would corrupt the pivot.
        return [
            'issuedDate' => 's.issued_date',
            'customer' => 'c.id',
            'status' => 's.status',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        $receiptFilter = $this->receiptFilter('s');
        $methods = $this->activeMethods();

        $pivotSelects = '';
        foreach ($methods as $method) {
            $id = $method['id'];
            $pivotSelects .= "sum(case when sp.payment_method_id = {$id} then sp.amount else 0 end) as pm_{$id},\n                ";
        }

        $ids = array_map(static fn(array $m): int => $m['id'], $methods);
        $idList = $ids === [] ? '0' : implode(',', $ids);

        return <<<SQL
            select
                s.issued_date as sale_date,
                TIME(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_time,
                concat(coalesce(i.series, s.series, '-'), '-', coalesce(i.number, s.number, '-')) as document,
                coalesce(c.razon_social, 'Sin cliente') as customer_name,
                {$pivotSelects}sum(case
                    when sp.payment_method_id is not null and sp.payment_method_id not in ({$idList})
                    then sp.amount else 0
                end) as pm_other,
                coalesce(sum(sp.amount), 0) as total_paid,
                s.grand_total as sale_total
            from sales s
                left join tax_document i on (i.movimiento_id = s.legacy_movimiento_id OR i.sale_id = s.id) and i.document_type in ('01', '03')
                left join cliente c on c.id = s.customer_id
                left join sale_payments sp on sp.sale_id = s.id
            where
                s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
                and s.issued_date between :date_from and :date_to
                and s.branch_id = :local_id
                {$receiptFilter}
                {$gridFilter}
            group by
                s.id,
                sale_date,
                sale_time,
                document,
                customer_name,
                s.grand_total
            order by s.issued_at desc
            SQL;
    }

    public function columns(): array
    {
        $columns = [
            'sale_date' => 'Fecha',
            'sale_time' => 'Hora',
            'document' => 'Comprobante',
            'customer_name' => 'Cliente',
        ];

        foreach ($this->activeMethods() as $method) {
            $columns['pm_' . $method['id']] = XlsColumnSpec::number($method['name'], '#,##0.00');
        }

        $columns['pm_other'] = XlsColumnSpec::number('Otros métodos', '#,##0.00');
        $columns['total_paid'] = XlsColumnSpec::number('Total pagado', '#,##0.00');
        $columns['sale_total'] = XlsColumnSpec::number('Total comprobante', '#,##0.00');

        return $columns;
    }

    public function filename(): string
    {
        return 'reporte_ventas_por_metodo_pago';
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function activeMethods(): array
    {
        if ($this->methodsCache !== null) {
            return $this->methodsCache;
        }

        /** @var list<array{id: int|string, name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name FROM payment_method WHERE is_active = 1 ORDER BY name',
        );

        $this->methodsCache = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ], $rows);

        return $this->methodsCache;
    }
}
