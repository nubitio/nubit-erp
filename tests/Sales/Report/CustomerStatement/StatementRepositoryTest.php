<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report\CustomerStatement;

use App\Sales\Report\CustomerStatement\StatementRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class StatementRepositoryTest extends TestCase
{
    public function testPaymentsQueryIncludesAndLabelsVoidedSaleChargeReversal(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn(string $sql): bool =>
                    str_contains($sql, "'charge_reversal'")
                    && str_contains($sql, "WHEN 'charge_reversal' THEN 'Anulación de venta'")
                ),
                ['customer' => 55, 'date_to' => '2026-05-31'],
            )
            ->willReturn([[
                'date' => '2026-05-12',
                'document' => 'B001-7',
                'amount' => '100.00',
                'detail' => 'Anulación de venta',
            ]]);

        $payments = (new StatementRepository($connection))->payments(55, '2026-05-31');

        self::assertSame([[
            'date' => '2026-05-12',
            'document' => 'B001-7',
            'amount' => '100.00',
            'detail' => 'Anulación de venta',
        ]], $payments);
    }
}
