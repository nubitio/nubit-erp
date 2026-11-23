<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Get;
use App\CashRegister\Service\OpenCashSessionLocator;
use App\CashRegister\State\CashShiftSalesProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class CashShiftSalesProviderTest extends TestCase
{
    /**
     * @param array<string, mixed>|false $sessionRow
     * @param list<array<string, mixed>> $saleRows
     * @param list<array<string, mixed>> $paymentRows
     */
    private function provider(
        ?AuthUserDto $user,
        array|false $sessionRow,
        array $saleRows = [],
        array $paymentRows = [],
    ): CashShiftSalesProvider {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn($user);

        $branch = $this->createStub(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn('branch-1');

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($sessionRow);
        $connection->method('fetchAllAssociative')->willReturnCallback(static fn(string $sql): array => str_contains(
            $sql,
            'FROM sale_payments',
        )
                ? $paymentRows
                : $saleRows);

        return new CashShiftSalesProvider($connection, $security, $branch, new OpenCashSessionLocator($connection));
    }

    /** @return array<string, string> */
    private function session(): array
    {
        return [
            'id' => '42',
            'cash_register_id' => '3',
            'name' => 'Caja 3',
            'opened_at' => '2026-11-23 08:30:00',
            'opening_amount' => '100.00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sale(string $id, string $total, string $status = 'COMPLETED', ?string $number = '12'): array
    {
        return [
            'id' => $id,
            'issued_at' => '2026-11-23 09:15:00',
            'status' => $status,
            'grand_total' => $total,
            'document_type' => $number === null ? null : '03',
            'series' => 'B001',
            'number' => $number,
            'sunat_status' => $number === null ? null : '0',
        ];
    }

    public function testRejectsAnonymousUsers(): void
    {
        $this->expectException(UnauthorizedHttpException::class);

        $this->provider(null, false)->provide(new Get());
    }

    public function testAUserWithoutAnOpenShiftHasNoSales(): void
    {
        $result = $this->provider(new AuthUserDto(userId: 7, username: 'cajero'), false)->provide(new Get());

        static::assertNull($result->sessionId);
        static::assertSame(0, $result->count);
        static::assertSame('0.00', $result->total);
        static::assertSame([], $result->sales);
    }

    public function testListsTheShiftsSalesWithTheirDocumentAndPaymentsAndTotalsTheValidOnes(): void
    {
        $result = $this->provider(
            new AuthUserDto(userId: 7, username: 'cajero'),
            $this->session(),
            [
                $this->sale('s-3', '30.00'),
                $this->sale('s-2', '999.00', 'VOIDED'),
                $this->sale('s-1', '25.50', 'COMPLETED', null),
            ],
            [
                ['sale_id' => 's-3', 'name' => 'Efectivo', 'amount' => '10'],
                ['sale_id' => 's-3', 'name' => 'Yape', 'amount' => '20'],
                ['sale_id' => 's-1', 'name' => null, 'amount' => '25.5'],
            ],
        )->provide(new Get());

        static::assertSame(42, $result->sessionId);
        static::assertSame(2, $result->count, 'a voided sale is listed but does not count');
        static::assertSame('55.50', $result->total);
        static::assertSame(['s-3', 's-2', 's-1'], array_column($result->sales, 'id'));
        static::assertSame('B001-12', $result->sales[0]['documentNumber']);
        static::assertSame('0', $result->sales[0]['sunatStatus']);
        static::assertSame(
            [['method' => 'Efectivo', 'amount' => '10.00'], ['method' => 'Yape', 'amount' => '20.00']],
            $result->sales[0]['payments'],
        );
        static::assertSame([], $result->sales[1]['payments']);
        static::assertNull($result->sales[2]['documentNumber'], 'a sale without a receipt yet has no number');
        static::assertSame([['method' => 'Sin método', 'amount' => '25.50']], $result->sales[2]['payments']);
        static::assertStringStartsWith('2026-11-23T09:15:00', (string) $result->sales[0]['issuedAt']);
    }

    public function testLooksForTheShiftOfTheCurrentBranchOnly(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'cajero'));
        $branch = $this->createStub(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn('branch-1');
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(static::stringContains('cr.branch_id = ?'), [7, 'branch-1'])
            ->willReturn(false);

        new CashShiftSalesProvider($connection, $security, $branch, new OpenCashSessionLocator($connection))->provide(
            new Get(),
        );
    }

    /**
     * The document of a sale is the latest non-credit-note one, and joining it must never duplicate the sale.
     */
    public function testAReplacedDocumentDoesNotDuplicateTheSaleAndCreditNotesAreIgnored(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement(
            'CREATE TABLE sales (id TEXT, cash_session_id INTEGER, issued_at TEXT, created_at TEXT, status TEXT, grand_total NUMERIC, deleted_at TEXT)',
        );
        $db->executeStatement(
            'CREATE TABLE tax_document (tax_document_id TEXT, sale_id TEXT, document_type TEXT, series TEXT, number TEXT, sunat_status TEXT, reason_code TEXT, created_at TEXT)',
        );
        $db->executeStatement(
            'CREATE TABLE sale_payments (id INTEGER PRIMARY KEY, sale_id TEXT, payment_method_id INTEGER, amount NUMERIC)',
        );
        $db->executeStatement('CREATE TABLE payment_method (id INTEGER, name TEXT)');
        $db->executeStatement("INSERT INTO payment_method VALUES (1, 'Efectivo'), (2, 'Yape')");
        $db->executeStatement(
            "INSERT INTO sales VALUES
                ('a', 42, '2026-11-23 09:00:00', '2026-11-23 09:00:00', 'COMPLETED', 30, NULL),
                ('b', 42, '2026-11-23 10:00:00', '2026-11-23 10:00:00', 'COMPLETED', 20, NULL),
                ('c', 42, '2026-11-23 11:00:00', '2026-11-23 11:00:00', 'COMPLETED', 5, '2026-11-23 11:05:00'),
                ('x', 99, '2026-11-23 12:00:00', '2026-11-23 12:00:00', 'COMPLETED', 700, NULL)",
        );
        $db->executeStatement(
            "INSERT INTO tax_document VALUES
                ('d1', 'a', '03', 'B001', '1', '2000', NULL, '2026-11-23 09:01:00'),
                ('d2', 'a', '03', 'B001', '2', '0', NULL, '2026-11-23 09:10:00'),
                ('d3', 'a', '07', 'BC01', '1', '0', 'CORRECTION', '2026-11-23 09:30:00')",
        );
        $db->executeStatement(
            "INSERT INTO sale_payments (sale_id, payment_method_id, amount) VALUES ('a', 1, 10), ('a', 2, 20), ('b', 1, 20)",
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->session());
        $connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(static fn(
                string $sql,
                array $params = [],
                array $types = [],
            ): array => $db->fetchAllAssociative(
                str_contains($sql, 'IN (?)') ? str_replace('IN (?)', "IN ('a', 'b')", $sql) : $sql,
                str_contains($sql, 'IN (?)') ? [] : array_values($params),
            ));
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'cajero'));
        $branch = $this->createStub(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn('branch-1');

        $result = new CashShiftSalesProvider(
            $connection,
            $security,
            $branch,
            new OpenCashSessionLocator($connection),
        )->provide(new Get());

        static::assertSame(['b', 'a'], array_column($result->sales, 'id'), 'only the shift\'s own, undeleted sales');
        static::assertSame(
            'B001-2',
            $result->sales[1]['documentNumber'],
            'the replacement, not the rejected one nor the credit note',
        );
        static::assertSame('0', $result->sales[1]['sunatStatus']);
        static::assertNull($result->sales[0]['documentNumber']);
        static::assertSame('50.00', $result->total);
        static::assertCount(2, $result->sales[1]['payments']);
        $db->close();
    }
}
