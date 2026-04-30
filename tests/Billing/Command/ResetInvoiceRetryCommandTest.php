<?php

declare(strict_types=1);

namespace App\Tests\Billing\Command;

use App\Billing\Command\ResetInvoiceRetryCommand;
use App\Efact\Service\SettingsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ResetInvoiceRetryCommand focusing on the pure business-logic
 * method `isRejectedStatus()` via reflection, without booting the full
 * Symfony/TenantAwareCommand stack.
 */
final class ResetInvoiceRetryCommandTest extends TestCase
{
    private function callIsRejectedStatus(string $status): bool
    {
        $command = new ResetInvoiceRetryCommand(
            $this->createStub(Connection::class),
            $this->createStub(SettingsService::class),
        );

        $ref = new \ReflectionMethod($command, 'isRejectedStatus');

        return $ref->invoke($command, $status);
    }

    // ── isRejectedStatus ──────────────────────────────────────────────────────

    /** @return array<string, array{string, bool}> */
    public static function rejectedStatusProvider(): array
    {
        return [
            'empty string → not rejected'       => ['',       false],
            'non-numeric string → not rejected' => ['error',  false],
            'pendiente → not rejected'           => ['pendiente', false],
            '0 → not rejected (accepted)'        => ['0',      false],
            '1999 → not rejected (below range)'  => ['1999',   false],
            '2000 → rejected (lower bound)'      => ['2000',   true],
            '2800 → rejected (common SUNAT code)' => ['2800',   true],
            '3999 → rejected (upper bound)'      => ['3999',   true],
            '4000 → not rejected (above range)'  => ['4000',   false],
            '9999 → not rejected (above range)'  => ['9999',   false],
        ];
    }

    #[DataProvider('rejectedStatusProvider')]
    public function testIsRejectedStatus(string $status, bool $expected): void
    {
        self::assertSame($expected, $this->callIsRejectedStatus($status));
    }
}
