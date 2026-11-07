<?php

declare(strict_types=1);

namespace App\Tests\Billing\Despatch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DespatchGuideStatusTest extends TestCase
{
    /** @return iterable<string, array{?string, bool, bool}> */
    public static function statuses(): iterable
    {
        yield 'never sent' => [null, false, false];
        yield 'accepted' => ['0', true, true];
        yield 'accepted with observations' => ['4001', true, true];
        yield 'rejected by SUNAT' => ['2335', true, false];
        yield 'SUNAT exception (retryable)' => ['0109', true, false];
        yield 'transport error without numeric code' => ['ERROR', true, false];
    }

    #[DataProvider('statuses')]
    public function testAcceptedOnlyForSunatSuccessCodes(?string $status, bool $processed, bool $accepted): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setSunatStatus($status);

        self::assertSame($processed, $guide->isProcessed());
        self::assertSame($accepted, $guide->isAccepted());
    }
}
