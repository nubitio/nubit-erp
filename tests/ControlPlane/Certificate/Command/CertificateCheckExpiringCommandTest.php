<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateAlertServiceInterface;
use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\Command\CertificateCheckExpiringCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CertificateCheckExpiringCommandTest extends TestCase
{
    public function testRefreshesWithoutSendingAlertsByDefault(): void
    {
        $service = $this->createMock(CertificateLifecycleServiceInterface::class);
        $service->expects(self::once())->method('refreshExpiringStatuses')->with(30)->willReturn([]);

        $alerts = $this->createMock(CertificateAlertServiceInterface::class);
        $alerts->expects(self::never())->method('sendExpiringAlerts');

        $tester = new CommandTester(new CertificateCheckExpiringCommand($service, $alerts));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testSendsAlertsWhenNotifyOptionIsPresent(): void
    {
        $service = $this->createMock(CertificateLifecycleServiceInterface::class);
        $service->expects(self::once())->method('refreshExpiringStatuses')->with(7)->willReturn([]);

        $alerts = $this->createMock(CertificateAlertServiceInterface::class);
        $alerts->expects(self::once())->method('sendExpiringAlerts')->with(7)->willReturn(2);

        $tester = new CommandTester(new CertificateCheckExpiringCommand($service, $alerts));

        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '7', '--notify' => true]));
        self::assertStringContainsString('Certificate alerts sent for 2 certificate(s).', $tester->getDisplay());
    }
}
