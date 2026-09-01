<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report;

use App\Sales\Report\PaymentMethodBreakdownReport;
use App\Settings\Service\SunatStateService;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Export\XlsColumn;
use Nubit\Platform\Export\XlsColumnSpec;
use PHPUnit\Framework\TestCase;

final class PaymentMethodBreakdownReportTest extends TestCase
{
    /**
     * @param list<array{id: int, name: string}> $methods
     */
    private function report(array $methods): PaymentMethodBreakdownReport
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($methods);

        $sunat = $this->createStub(SunatStateService::class);
        $sunat->method('isReceiptRequired')->willReturn(false);

        return new PaymentMethodBreakdownReport($sunat, $connection);
    }

    public function test_key_and_field_map(): void
    {
        $report = $this->report([]);

        self::assertSame('payment-method-breakdown', $report->key());
        self::assertArrayHasKey('issuedDate', $report->fieldMap());
        self::assertArrayNotHasKey('paymentMethod', $report->fieldMap());
    }

    public function test_columns_have_one_entry_per_active_method_in_order(): void
    {
        $report = $this->report([
            ['id' => 3, 'name' => 'Efectivo'],
            ['id' => 7, 'name' => 'Yape'],
        ]);

        $columns = $report->columns();

        self::assertSame(
            ['sale_date', 'sale_time', 'document', 'customer_name', 'pm_3', 'pm_7', 'pm_other', 'total_paid', 'sale_total'],
            array_keys($columns),
        );

        foreach (['pm_3' => 'Efectivo', 'pm_7' => 'Yape', 'pm_other' => 'Otros métodos', 'total_paid' => 'Total pagado', 'sale_total' => 'Total comprobante'] as $key => $label) {
            self::assertInstanceOf(XlsColumnSpec::class, $columns[$key]);
            self::assertSame($label, $columns[$key]->label);
            self::assertSame(XlsColumn::SUMMARY_SUM, $columns[$key]->summary->type);
        }
    }

    public function test_sql_pivots_by_method_id_and_buckets_the_rest(): void
    {
        $sql = $this->report([
            ['id' => 3, 'name' => 'Efectivo'],
            ['id' => 7, 'name' => 'Yape'],
        ])->sql('');

        self::assertStringContainsString('sum(case when sp.payment_method_id = 3 then sp.amount else 0 end) as pm_3', $sql);
        self::assertStringContainsString('sum(case when sp.payment_method_id = 7 then sp.amount else 0 end) as pm_7', $sql);
        self::assertStringContainsString('sp.payment_method_id not in (3,7)', $sql);
        self::assertStringContainsString('as pm_other', $sql);
        self::assertStringContainsString('coalesce(sum(sp.amount), 0) as total_paid', $sql);
        self::assertStringContainsString('group by', $sql);
    }

    public function test_sql_with_no_active_methods_still_valid(): void
    {
        $sql = $this->report([])->sql('');

        self::assertStringNotContainsString('as pm_,', $sql);
        self::assertStringContainsString('sp.payment_method_id not in (0)', $sql);
        self::assertStringContainsString('as pm_other', $sql);

        $columns = $this->report([])->columns();
        self::assertSame(
            ['sale_date', 'sale_time', 'document', 'customer_name', 'pm_other', 'total_paid', 'sale_total'],
            array_keys($columns),
        );
    }
}
