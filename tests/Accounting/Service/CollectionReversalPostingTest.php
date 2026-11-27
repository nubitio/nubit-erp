<?php

declare(strict_types=1);

namespace App\Tests\Accounting\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/** Undoing a receivable collection reopens the receivable (121) and gives the money back (101 or 1041). */
final class CollectionReversalPostingTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function kinds(): iterable
    {
        yield 'cash' => [EconomicEvent::RECEIVABLE_COLLECT_REVERSE_CASH, '101'];
        yield 'bank' => [EconomicEvent::RECEIVABLE_COLLECT_REVERSE_BANK, '1041'];
    }

    #[DataProvider('kinds')]
    public function testTheReversalDebitsTheReceivableAndCreditsTheMoneyAccount(string $kind, string $account): void
    {
        $lines = $this->service()->linesFor(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT_REVERSAL,
            sourceId: 'tender-1',
            kind: $kind,
            occurredOn: '2026-11-27',
            description: 'Reversión de cobranza',
            total: '150.00',
        ));

        self::assertSame('121', $lines[0]['code']);
        self::assertSame('150.00', $lines[0]['debit'] ?? null);
        self::assertSame($account, $lines[1]['code']);
        self::assertSame('150.00', $lines[1]['credit'] ?? null);
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
