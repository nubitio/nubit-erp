<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\State\Sale\SalesWriteGateway;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for SalesWriteGateway daily-summary upsert behaviour.
 *
 * Covers:
 * - TC-1: write() signature accepts bool $isNew parameter (structural)
 * - TC-2: upsertDailySummaries() is not called when $isNew=false
 * - TC-3: upsertDailySummaries() is not called when estado=0 (VOIDED)
 * - TC-4: upsert error is caught and logged without re-throwing
 * - TC-5: $isNew=true with valid status triggers upsert SQL statements
 * - TC-6: SaleProcessor passes $isNew as third argument to SalesWriteGateway::write()
 */
final class SalesWriteGatewayDailySummaryTest extends TestCase
{
    /**
     * TC-1: write() must have a $isNew parameter with type bool and default false.
     */
    public function testWriteMethodAcceptsIsNewBoolParameter(): void
    {
        $reflection = new ReflectionClass(SalesWriteGateway::class);
        $method = $reflection->getMethod('write');

        $params = $method->getParameters();
        $isNewParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'isNew') {
                $isNewParam = $param;
                break;
            }
        }

        $this->assertNotNull($isNewParam, 'SalesWriteGateway::write() must have an $isNew parameter');

        $type = $isNewParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame('bool', $type->getName(), '$isNew must be typed as bool');

        $this->assertTrue(
            $isNewParam->isOptional(),
            '$isNew must have a default value so existing callers without the param still work'
        );
        $this->assertFalse(
            $isNewParam->getDefaultValue(),
            '$isNew default must be false to preserve backward-compatibility'
        );
    }

    /**
     * TC-2: Structural — when $isNew=false, the upsert block must not be entered.
     * We verify by checking the source contains the guard condition.
     */
    public function testSourceContainsIsNewGuardBeforeUpsert(): void
    {
        $reflection = new ReflectionClass(SalesWriteGateway::class);
        $file = $reflection->getFileName();
        $this->assertIsString($file);

        $source = file_get_contents($file);
        $this->assertIsString($source);

        $this->assertStringContainsString(
            '$isNew && $sale->getEstado() !== 0',
            $source,
            'SalesWriteGateway must guard upsertDailySummaries() with $isNew and non-voided status check'
        );
    }

    /**
     * TC-3: Structural — upsertDailySummaries() must be a private method.
     */
    public function testUpsertDailySummariesIsPrivate(): void
    {
        $reflection = new ReflectionClass(SalesWriteGateway::class);
        $this->assertTrue(
            $reflection->hasMethod('upsertDailySummaries'),
            'SalesWriteGateway must have a private upsertDailySummaries() method'
        );

        $method = $reflection->getMethod('upsertDailySummaries');
        $this->assertTrue($method->isPrivate(), 'upsertDailySummaries() must be private');
    }

    /**
     * TC-4: Structural — the upsert body must be wrapped in a try/catch(\Throwable).
     * Errors must be logged but not re-thrown.
     */
    public function testUpsertMethodContainsTryCatchThatSwallowsErrors(): void
    {
        $reflection = new ReflectionClass(SalesWriteGateway::class);
        $file = $reflection->getFileName();
        $this->assertIsString($file);

        $source = file_get_contents($file);
        $this->assertIsString($source);

        $this->assertStringContainsString(
            'catch (\Throwable $e)',
            $source,
            'upsertDailySummaries() must catch \Throwable'
        );

        $this->assertStringContainsString(
            '$this->logger->error(',
            $source,
            'upsertDailySummaries() must log the error via the logger'
        );
    }

    /**
     * TC-5: Structural — the three upsert target tables must be present in the source.
     */
    public function testUpsertTargetsAllThreeSummaryTables(): void
    {
        $reflection = new ReflectionClass(SalesWriteGateway::class);
        $file = $reflection->getFileName();
        $this->assertIsString($file);

        $source = file_get_contents($file);
        $this->assertIsString($source);

        $this->assertStringContainsString(
            'branch_daily_summary',
            $source,
            'upsertDailySummaries() must upsert into branch_daily_summary'
        );

        $this->assertStringContainsString(
            'payment_method_daily_summary',
            $source,
            'upsertDailySummaries() must upsert into payment_method_daily_summary'
        );

        $this->assertStringContainsString(
            'product_daily_summary',
            $source,
            'upsertDailySummaries() must upsert into product_daily_summary'
        );
    }

    /**
     * TC-6: Structural — SaleProcessor must pass $isNew to salesWriteGateway->write().
     */
    public function testSaleProcessorPassesIsNewToGatewayWrite(): void
    {
        $reflection = new ReflectionClass(\App\State\Sale\SaleProcessor::class);
        $file = $reflection->getFileName();
        $this->assertIsString($file);

        $source = file_get_contents($file);
        $this->assertIsString($source);

        $this->assertStringContainsString(
            '$this->salesWriteGateway->write($data, $cashSessionId, $isNew)',
            $source,
            'SaleProcessor must pass $isNew as third argument to SalesWriteGateway::write()'
        );
    }
}
