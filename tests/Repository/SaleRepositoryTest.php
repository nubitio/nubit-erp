<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\Assistant\SalesSummaryDto;
use App\Entity\Sale;
use App\Repository\SaleRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the V2-redirected getSummary() and getRecentSales() methods.
 *
 * These methods query the `sales` table via DBAL (no ORM DQL), so we mock the
 * DBAL Connection only.
 */
final class SaleRepositoryTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildRepository(Connection $connection): SaleRepository
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = Sale::class;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->method('getConnection')->willReturn($connection);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(Sale::class)->willReturn($entityManager);

        return new SaleRepository($registry);
    }

    // ── getSummary() ──────────────────────────────────────────────────────────

    public function testGetSummaryReturnsSalesSummaryDto(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->callback(
                    static fn (string $sql): bool =>
                        str_contains($sql, 'SELECT') &&
                        str_contains($sql, 'COUNT(*)') &&
                        str_contains($sql, 'grand_total') &&
                        str_contains($sql, 'sales s') &&
                        str_contains($sql, 'issued_at BETWEEN')
                ),
                $this->callback(
                    static fn (array $params): bool =>
                        array_key_exists('from', $params) && array_key_exists('to', $params)
                ),
            )
            ->willReturn(['cnt' => 3, 'totalAmount' => '450.00', 'avgAmount' => '150.00']);

        $repo = $this->buildRepository($connection);

        $from = new DateTimeImmutable('2026-01-01');
        $to = new DateTimeImmutable('2026-01-31');

        $dto = $repo->getSummary($from, $to);

        $this->assertInstanceOf(SalesSummaryDto::class, $dto);
        $this->assertSame('2026-01-01', $dto->from);
        $this->assertSame('2026-01-31', $dto->to);
        $this->assertSame(3, $dto->totalSales);
        $this->assertEqualsWithDelta(450.0, $dto->totalAmount, 0.001);
        $this->assertEqualsWithDelta(150.0, $dto->averageTicket, 0.001);
    }

    public function testGetSummaryHandlesFalseResultGracefully(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = $this->buildRepository($connection);

        $dto = $repo->getSummary(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-31'),
        );

        $this->assertSame(0, $dto->totalSales);
        $this->assertEqualsWithDelta(0.0, $dto->totalAmount, 0.001);
        $this->assertEqualsWithDelta(0.0, $dto->averageTicket, 0.001);
    }

    public function testGetSummaryHandlesNullAggregatesGracefully(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'cnt' => 0,
            'totalAmount' => null,
            'avgAmount' => null,
        ]);

        $repo = $this->buildRepository($connection);

        $dto = $repo->getSummary(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-31'),
        );

        $this->assertSame(0, $dto->totalSales);
        $this->assertEqualsWithDelta(0.0, $dto->totalAmount, 0.001);
        $this->assertEqualsWithDelta(0.0, $dto->averageTicket, 0.001);
    }

    // ── getRecentSales() ──────────────────────────────────────────────────────

    public function testGetRecentSalesReturnsRecentSaleDtos(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(
                    static fn (string $sql): bool =>
                        str_contains($sql, 'sales s') &&
                        str_contains($sql, 'grand_total') &&
                        str_contains($sql, 'issued_at') &&
                        str_contains($sql, 'tax_document td') &&
                        str_contains($sql, 'ORDER BY') &&
                        str_contains($sql, 'LIMIT')
                ),
                $this->callback(
                    static fn (array $params): bool =>
                        array_key_exists('limit', $params) && $params['limit'] === 2
                ),
                $this->anything(),
            )
            ->willReturn([
                [
                    'id' => 'uuid-001',
                    'total' => '200.00',
                    'fecha' => '2026-02-10 10:00:00',
                    'invoice' => 'F001-0001',
                    'serie' => 'F001',
                    'numero' => '0001',
                ],
                [
                    'id' => 'uuid-002',
                    'total' => '50.00',
                    'fecha' => '2026-02-09 09:00:00',
                    'invoice' => null,
                    'serie' => null,
                    'numero' => null,
                ],
            ]);

        $repo = $this->buildRepository($connection);

        $results = $repo->getRecentSales(2);

        $this->assertCount(2, $results);

        $first = $results[0];
        $this->assertSame('uuid-001', $first->id);
        $this->assertEqualsWithDelta(200.0, $first->total, 0.001);
        $this->assertSame('F001-0001', $first->invoice);
        $this->assertSame('F001', $first->series);
        $this->assertSame('0001', $first->number);
        $this->assertStringContainsString('2026-02-10', $first->issuedAt);

        $second = $results[1];
        $this->assertSame('uuid-002', $second->id);
        $this->assertNull($second->invoice);
        $this->assertNull($second->series);
        $this->assertNull($second->number);
    }

    public function testGetRecentSalesReturnsEmptyArrayWhenNoRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $repo = $this->buildRepository($connection);

        $this->assertSame([], $repo->getRecentSales(5));
    }

    public function testGetRecentSalesDefaultsToFiveRecords(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->anything(),
                $this->callback(
                    static fn (array $params): bool =>
                        array_key_exists('limit', $params) && $params['limit'] === 5
                ),
                $this->anything(),
            )
            ->willReturn([]);

        $repo = $this->buildRepository($connection);
        $repo->getRecentSales();
    }
}
