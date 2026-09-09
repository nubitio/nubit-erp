<?php

declare(strict_types=1);

namespace App\Tests\Efact\Observability;

use App\Efact\Observability\OperationContextProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;

final class OperationContextProcessorTest extends TestCase
{
    public function testAddsUserIdAndCorrelationIdFromTenantContext(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(7, 'acme', 'acme.example', 'req-abc-123');
        $tenantContext->setActor('  cashier@acme  ', 'http');

        $record = $this->processor($tenantContext)($this->record());

        static::assertSame('cashier@acme', $record->context['user_id']);
        static::assertSame('req-abc-123', $record->context['correlation_id']);
    }

    public function testNullsUserIdWhenNoActorIsSet(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(7, 'acme', 'acme.example', 'req-1');

        $record = $this->processor($tenantContext)($this->record());

        static::assertArrayHasKey('user_id', $record->context);
        static::assertNull($record->context['user_id']);
        static::assertSame('req-1', $record->context['correlation_id']);
    }

    public function testKeepsExistingContextFields(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, 'req-9');

        $record = $this->processor($tenantContext)($this->record(['module' => 'sales']));

        static::assertSame('sales', $record->context['module']);
        static::assertSame('req-9', $record->context['correlation_id']);
    }

    private function processor(TenantContext $tenantContext): OperationContextProcessor
    {
        return new OperationContextProcessor($tenantContext);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(array $context = []): LogRecord
    {
        return new LogRecord(new DateTimeImmutable(), 'operation', Level::Info, 'sales sale.created', $context);
    }
}
