<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TestSeedE2eCommand;
use App\Service\E2eTestSeedServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TestSeedE2eCommandTest extends TestCase
{
    public function testFailsWhenSeedFlagIsMissing(): void
    {
        unset($_SERVER['E2E_SEED_ENABLED']);

        $service = $this->createMock(E2eTestSeedServiceInterface::class);
        $service->expects(self::never())->method('seed');

        $tester = new CommandTester(new TestSeedE2eCommand($service));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('requires E2E_SEED_ENABLED=1', $tester->getDisplay());
    }

    public function testPrintsMachineReadableJsonWhenSeedFlagIsEnabled(): void
    {
        $_SERVER['E2E_SEED_ENABLED'] = '1';

        $service = $this->createMock(E2eTestSeedServiceInterface::class);
        $service->expects(self::once())
            ->method('seed')
            ->willReturn([
                'username' => 'ci_e2e_user',
                'password' => 'secret123',
                'saleId' => 'sale-1',
            ]);

        $tester = new CommandTester(new TestSeedE2eCommand($service));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertJsonStringEqualsJsonString(
            '{"username":"ci_e2e_user","password":"secret123","saleId":"sale-1"}',
            trim($tester->getDisplay())
        );

        unset($_SERVER['E2E_SEED_ENABLED']);
    }
}
