<?php

declare(strict_types=1);

namespace App\Tests\Efact\Observability;

use App\Efact\Observability\OperationLog;
use PHPUnit\Framework\TestCase;

final class OperationLogTest extends TestCase
{
    public function testRecordEmitsOneInfoLineWithNormalisedFields(): void
    {
        $logger = new RecordingLogger();

        new OperationLog($logger)->record('sales', 'receivable.collected', 42, ['amount' => '90.00']);

        static::assertCount(1, $logger->calls);
        static::assertSame('info', $logger->calls[0]['level']);
        static::assertSame('sales receivable.collected', $logger->calls[0]['message']);
        static::assertSame('sales', $logger->calls[0]['context']['module']);
        static::assertSame('receivable.collected', $logger->calls[0]['context']['operation']);
        static::assertSame('42', $logger->calls[0]['context']['entity_id']);
        static::assertSame('90.00', $logger->calls[0]['context']['amount']);
    }

    public function testEntityIdIsNullableAndStringified(): void
    {
        $logger = new RecordingLogger();

        new OperationLog($logger)->record('inventory', 'stock.drawn');

        static::assertNull($logger->calls[0]['context']['entity_id']);
        static::assertSame('inventory', $logger->calls[0]['context']['module']);
    }
}
