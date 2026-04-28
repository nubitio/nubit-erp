<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TestResetE2eCommand;
use App\Setup\Service\E2eTestSeedServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TestResetE2eCommandTest extends TestCase
{
    public function testFailsWhenSeedFlagIsMissing(): void
    {
        unset($_SERVER['E2E_SEED_ENABLED']);

        $service = $this->createMock(E2eTestSeedServiceInterface::class);
        $service->expects(self::never())->method('reset');

        $tester = new CommandTester(new TestResetE2eCommand($service));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('requires E2E_SEED_ENABLED=1', $tester->getDisplay());
    }

    public function testResetsAndPrintsOkWhenSeedFlagIsEnabled(): void
    {
        $_SERVER['E2E_SEED_ENABLED'] = '1';

        $service = $this->createMock(E2eTestSeedServiceInterface::class);
        $service->expects(self::once())->method('reset');

        $tester = new CommandTester(new TestResetE2eCommand($service));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{"status":"ok"}', trim($tester->getDisplay()));

        unset($_SERVER['E2E_SEED_ENABLED']);
    }
}
