<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\Command\CertificateRevokeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CertificateRevokeCommandTest extends TestCase
{
    public function testRequiresReason(): void
    {
        $service = $this->createMock(CertificateLifecycleServiceInterface::class);
        $service->expects(self::never())->method('revoke');

        $tester = new CommandTester(new CertificateRevokeCommand($service));

        self::assertSame(Command::FAILURE, $tester->execute(['tenant' => 'acme', 'ruc' => '20123456789']));
        self::assertStringContainsString('--reason is required', $tester->getDisplay());
    }

    public function testRevokesCertificate(): void
    {
        $service = $this->createMock(CertificateLifecycleServiceInterface::class);
        $service->expects(self::once())
            ->method('revoke')
            ->with('acme', '20123456789', 'ops@example.com', 'rotated');

        $tester = new CommandTester(new CertificateRevokeCommand($service));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'tenant' => 'acme',
            'ruc' => '20123456789',
            '--reason' => 'rotated',
            '--revoked-by' => 'ops@example.com',
        ]));
        self::assertStringContainsString('Certificate revoked.', $tester->getDisplay());
    }
}
