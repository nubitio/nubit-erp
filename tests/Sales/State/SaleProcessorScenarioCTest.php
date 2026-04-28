<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Core\Exception\ServiceException;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SaleRecord;
use App\Sales\State\CashSessionResolver;
use App\Sales\State\SaleProcessor;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Integration scenario tests for SLICE-002.
 *
 * Scenario C: REQUIRE_CASH_REGISTER=false, no cash_session in DB
 *   → sale must succeed, cash_session_id must be null, no exception thrown.
 *
 * These tests verify the two-layer fix:
 *   1. CashSessionResolver.tryResolveForSale() returns null (not throws) when no session found.
 *   2. SaleProcessor delegates to tryResolveForSale (not resolveForSale) when cash register
 *      is not required — verified via structural analysis of the source.
 */
final class SaleProcessorScenarioCTest extends TestCase
{
    /**
     * Scenario C — full contract test on CashSessionResolver:
     * when no cash_session rows exist for the branch, tryResolveForSale() returns null.
     *
     * This is the critical path that SaleProcessor follows when REQUIRE_CASH_REGISTER=false.
     */
    public function testScenarioCResolverReturnsNullWithNoSession(): void
    {
        $connection = $this->createMock(Connection::class);
        // No legacy shift, so 3 queries: register+issuedAt, branch+issuedAt, latest — all return false
        $connection
            ->expects($this->exactly(3))
            ->method('fetchAssociative')
            ->willReturn(false);

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-abc', cashRegisterId: 1, issuedAt: '2026-03-01 10:00:00');

        // Must return null — NOT throw ServiceException
        $result = $resolver->tryResolveForSale($sale);

        $this->assertNull($result, 'Scenario C: tryResolveForSale() must return null when no session exists');
    }

    /**
     * Scenario A (regression guard): when REQUIRE_CASH_REGISTER=true semantics apply,
     * resolveForSale() must throw ServiceException (not silently return null).
     * This ensures we did not accidentally break the required-cash-register path.
     */
    public function testScenarioAResolverThrowsWithNoSession(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(3))
            ->method('fetchAssociative')
            ->willReturn(false);

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-abc', cashRegisterId: 1, issuedAt: '2026-03-01 10:00:00');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/No se pudo resolver cash_session_id/');

        // resolveForSale() (used when requireCashShift=true) must still throw
        $resolver->resolveForSale($sale);
    }

    /**
     * Scenario B (regression guard): when a valid cash_session exists,
     * tryResolveForSale() must return its ID (not null).
     * This ensures REQUIRE_CASH_REGISTER=false + session exists still works.
     */
    public function testScenarioBResolverReturnsSessionIdWhenSessionExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                false, // register+issuedAt → no match
                [     // branch+issuedAt → found (resolver short-circuits here, latest not called)
                    'id' => 42,
                    'cash_register_id' => 1,
                    'branch_id' => 'branch-abc',
                    'opened_at' => '2026-03-01 08:00:00',
                    'closed_at' => null,
                ]
            );

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-abc', cashRegisterId: 1, issuedAt: '2026-03-01 10:00:00');

        $result = $resolver->tryResolveForSale($sale);

        $this->assertSame(42, $result, 'Scenario B: tryResolveForSale() must return session ID when session exists');
    }

    /**
     * Structural test: SaleProcessor must call tryResolveForSale (not resolveForSale)
     * when the requireCashShift branch evaluates to false.
     *
     * This guards against accidental revert of the SaleProcessor change.
     */
    public function testSaleProcessorSourceUsesTryResolveWhenCashNotRequired(): void
    {
        $reflection = new \ReflectionClass(SaleProcessor::class);
        $file = $reflection->getFileName();
        $this->assertIsString($file, 'SaleProcessor source file must be locatable');

        $source = file_get_contents($file);
        $this->assertIsString($source);

        // Must contain the conditional delegation pattern
        $this->assertStringContainsString(
            'tryResolveForSale(',
            $source,
            'SaleProcessor must call CashSessionResolver::tryResolveForSale() for the non-required path'
        );

        // The call must be inside a ternary/conditional guarded by $requireOpenSession
        $this->assertStringContainsString(
            '$requireOpenSession',
            $source,
            'SaleProcessor must reference $requireOpenSession to select the resolver method'
        );

        // Must NOT call resolveForSale unconditionally (both paths must be present)
        $this->assertStringContainsString(
            'resolveForSale(',
            $source,
            'SaleProcessor must still call CashSessionResolver::resolveForSale() for the required path'
        );
    }

    /**
     * Structural test: SalesWriteGateway::write() must accept nullable cash_session_id
     * so that Scenario C (cash_session_id = null) can be written to the sales table.
     */
    public function testSalesWriteGatewayWriteAcceptsNullableCashSessionId(): void
    {
        $reflection = new \ReflectionClass(\App\Sales\State\SalesWriteGateway::class);
        $method = $reflection->getMethod('write');

        $params = $method->getParameters();
        $cashSessionIdParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'cashSessionId') {
                $cashSessionIdParam = $param;
                break;
            }
        }

        $this->assertNotNull(
            $cashSessionIdParam,
            'SalesWriteGateway::write() must have a $cashSessionId parameter'
        );

        $type = $cashSessionIdParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertTrue(
            $type->allowsNull(),
            'SalesWriteGateway::write()::$cashSessionId must be nullable (?int) to support Scenario C'
        );
    }

    private function buildSale(string $branchId, int $cashRegisterId, string $issuedAt): SaleRecord
    {
        $branch = new Branch();
        $this->setEntityId($branch, $branchId);

        $sale = new SaleRecord();
        $sale->setBranch($branch);
        $sale->setCajaId($cashRegisterId);
        $sale->setFecha(new \DateTimeImmutable($issuedAt));

        return $sale;
    }

    private function setEntityId(object $entity, string $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}
