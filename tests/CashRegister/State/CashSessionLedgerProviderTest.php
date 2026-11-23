<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Get;
use App\CashRegister\State\CashSessionLedgerProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CashSessionLedgerProviderTest extends TestCase
{
    public function testUnknownSessionIsNotFound(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(NotFoundHttpException::class);

        new CashSessionLedgerProvider($connection)->provide(new Get(), ['id' => 9]);
    }

    public function testMergesDrawerMovementsAndDigitalPaymentsNewestFirst(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => '5',
                'cash_register_id' => '1',
                'register_name' => 'Caja 01',
                'cashier' => 'rosa',
                'opened_at' => '2026-11-23 08:02:00',
                'closed_at' => null,
                'status' => 'OPEN',
                'opening_amount' => '200',
            ]);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls([
            $this->movement(1, 'OPENING', '200.00', '2026-11-23 08:02:00'),
            $this->movement(
                2,
                'SALE_IN',
                '86.00',
                '2026-11-23 11:42:00',
                series: 'B001',
                number: '4512',
                method: 'Efectivo',
            ),
            $this->movement(
                3,
                'CASH_OUT',
                '730.00',
                '2026-11-23 10:58:00',
                category: 'manager_withdrawal',
                description: 'Retiro parcial',
            ),
            $this->movement(
                4,
                'CASH_OUT',
                '50.00',
                '2026-11-23 11:20:00',
                category: 'petty_cash_expense',
                description: 'Pago de movilidad',
            ),
            $this->movement(5, 'CASH_IN', '150.00', '2026-11-23 10:44:00', description: 'Reposición de sencillo'),
        ], [
            [
                'id' => '9',
                'amount' => '42.50',
                'paid_at' => '2026-11-23 11:31:00',
                'created_at' => '2026-11-23 11:31:00',
                'payment_method' => 'Yape',
                'series' => 'B001',
                'number' => '4511',
            ],
        ]);

        $ledger = new CashSessionLedgerProvider($connection)->provide(new Get(), ['id' => 5]);

        self::assertSame('open', $ledger->status);
        self::assertSame('200.00', $ledger->openingAmount);
        self::assertSame(['m2', 'p9', 'm4', 'm3', 'm5', 'm1'], array_column($ledger->entries, 'id'));

        $byId = array_column($ledger->entries, null, 'id');
        self::assertSame('sale', $byId['m2']['kind']);
        self::assertSame('B001-4512', $byId['m2']['document']);
        self::assertSame('Yape', $byId['p9']['paymentMethod']);
        self::assertSame('42.50', $byId['p9']['amount']);
        self::assertSame('withdrawal', $byId['m3']['kind']);
        self::assertSame('-730.00', $byId['m3']['amount']);
        self::assertSame('expense', $byId['m4']['kind']);
        self::assertSame('-50.00', $byId['m4']['amount']);
        self::assertSame('income', $byId['m5']['kind']);
        self::assertSame('opening', $byId['m1']['kind']);
        self::assertSame('Fondo inicial del turno', $byId['m1']['description']);
        self::assertSame('Efectivo', $byId['m1']['paymentMethod']);
    }

    /** @return array<string, mixed> */
    private function movement(
        int $id,
        string $type,
        string $amount,
        string $at,
        ?string $category = null,
        ?string $description = null,
        ?string $series = null,
        ?string $number = null,
        ?string $method = null,
    ): array {
        return [
            'id' => (string) $id,
            'movement_type' => $type,
            'amount' => $amount,
            'description' => $description,
            'created_at' => $at,
            'category_code' => $category,
            'category_name' => null,
            'payment_method' => $method,
            'series' => $series,
            'number' => $number,
        ];
    }
}
