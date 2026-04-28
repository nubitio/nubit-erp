<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Command;

use App\Reporting\Command\FillTimeDimensionCommand;
use App\Core\Context\TenantContext;
use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use App\ControlPlane\Tenant\Service\TenantManager;
use App\Reporting\Service\TimeDimensionSeeder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FillTimeDimensionCommandTest extends TestCase
{
    public function testFailsWhenStartDateIsNotBeforeEndDate(): void
    {
        $seeder = $this->createMock(TimeDimensionSeeder::class);
        $seeder->expects(self::never())->method('seed');

        $tester = new CommandTester($this->newCommand($seeder));
        $exitCode = $tester->execute([
            'start' => '2026-01-02',
            'end' => '2026-01-02',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('La fecha de inicio debe ser menor a la fecha fin.', $tester->getDisplay());
    }

    public function testSeedsTimeDimensionSuccessfully(): void
    {
        $seeder = $this->createMock(TimeDimensionSeeder::class);
        $seeder->expects(self::once())
            ->method('seed')
            ->with(
                self::isInstanceOf(\DateTimeImmutable::class),
                self::isInstanceOf(\DateTimeImmutable::class),
                100,
                'en_US',
                self::isType('callable')
            )
            ->willReturn(365);

        $tester = new CommandTester($this->newCommand($seeder));
        $exitCode = $tester->execute([
            'start' => '2026-01-01',
            'end' => '2027-01-01',
            '--batch-size' => 100,
            '--locale' => 'en_US',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dimensión de fechas completada. Filas procesadas: 365', $tester->getDisplay());
    }

    public function testReturnsFailureWhenSeederThrows(): void
    {
        $seeder = $this->createMock(TimeDimensionSeeder::class);
        $seeder->method('seed')->willThrowException(new \RuntimeException('db error'));

        $tester = new CommandTester($this->newCommand($seeder));
        $exitCode = $tester->execute([
            'start' => '2026-01-01',
            'end' => '2026-01-03',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('db error', $tester->getDisplay());
    }

    private function newCommand(TimeDimensionSeeder $seeder): FillTimeDimensionCommand
    {
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([]);

        $command = new FillTimeDimensionCommand($seeder);
        $command->initDependencies(
            $tenantManager,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            $this->createMock(LoggerInterface::class)
        );

        return $command;
    }
}
