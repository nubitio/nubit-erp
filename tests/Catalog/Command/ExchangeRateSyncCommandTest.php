<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Command;

use App\Catalog\Command\ExchangeRateSyncCommand;
use App\Catalog\Service\ExchangeRateSyncService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ExchangeRateSyncCommandTest extends TestCase
{
    /** @param array{success: bool, rate: ?string, updated_at: ?string, message: string} $syncResult */
    private function runCommand(array $syncResult): CommandTester
    {
        $service = $this->createStub(ExchangeRateSyncService::class);
        $service->method('sync')->willReturn($syncResult);

        $command = new ExchangeRateSyncCommand($service);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($app->find('app:exchange-rate:sync'));
        $tester->execute([]);

        return $tester;
    }

    public function testOutputsSuccessWhenRateSynced(): void
    {
        $tester = $this->runCommand([
            'success' => true,
            'rate' => '3.75',
            'updated_at' => '2026-04-30 09:00:00',
            'message' => 'OK',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('3.75', $tester->getDisplay());
        self::assertStringContainsString('USD', $tester->getDisplay());
    }

    public function testOutputsWarningWhenSyncFails(): void
    {
        $tester = $this->runCommand([
            'success' => false,
            'rate' => null,
            'updated_at' => null,
            'message' => 'BCRP API unreachable',
        ]);

        // Command always exits 0 — it warns but doesn't fail hard
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('BCRP API unreachable', $tester->getDisplay());
    }
}
