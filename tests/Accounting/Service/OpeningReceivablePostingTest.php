<?php

declare(strict_types=1);

namespace App\Tests\Accounting\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

final class OpeningReceivablePostingTest extends TestCase
{
    public function testOpeningReceivableDebitsReceivablesAndCreditsOpeningEquity(): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_OPENING_RECEIVABLE,
            sourceId: 'opening-sale-1',
            kind: EconomicEvent::OPENING_RECEIVABLE,
            occurredOn: '2026-09-10',
            description: 'Saldo inicial',
            total: '716.30',
        ));

        self::assertSame('121', $lines[0]['code']);
        self::assertSame('716.30', $lines[0]['debit'] ?? null);
        self::assertSame('591', $lines[1]['code']);
        self::assertSame('716.30', $lines[1]['credit'] ?? null);
        self::assertSame(2, count($lines));
    }

    private function service(): DefaultPostingService
    {
        $reflection = new ReflectionClass(DefaultPostingService::class);
        /** @var DefaultPostingService $service */
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('logger')->setValue($service, new NullLogger());

        return $service;
    }
}
