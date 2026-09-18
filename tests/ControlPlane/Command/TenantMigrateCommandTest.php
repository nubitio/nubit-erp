<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantMigrateCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantMigrateCommandTest extends TestCase
{
    #[DataProvider('concurrencyProvider')]
    public function testResolveConcurrency(mixed $parallelOption, ?string $envValue, int $expected): void
    {
        self::assertSame($expected, TenantMigrateCommand::resolveConcurrency($parallelOption, $envValue));
    }

    /** @return array<string, array{0: mixed, 1: ?string, 2: int}> */
    public static function concurrencyProvider(): array
    {
        return [
            'default when option omitted' => [false, null, 8],
            'env overrides default' => [false, '12', 12],
            'empty env keeps default' => [false, '', 8],
            'bare --parallel uses default' => [null, null, 8],
            'explicit parallel value' => ['4', null, 4],
            'sequential' => ['1', '12', 1],
            'zero treated as omitted default' => ['0', null, 8],
            'negative becomes one' => ['-3', null, 1],
        ];
    }
}
