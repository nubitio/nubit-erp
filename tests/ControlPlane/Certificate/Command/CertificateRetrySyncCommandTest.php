<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\Command\CertificateRetrySyncCommand;
use App\ControlPlane\Certificate\TenantCertificate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CertificateRetrySyncCommandTest extends TestCase
{
    public function testRetriesRevocation(): void
    {
        $service = $this->createMock(CertificateLifecycleServiceInterface::class);
        $service->expects(self::once())->method('retryRevocation')->with('acme', '20123456789');

        $tester = new CommandTester(new CertificateRetrySyncCommand($service));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'tenant' => 'acme',
            'ruc' => '20123456789',
            '--operation' => 'revoke',
        ]));
        self::assertStringContainsString('Certificate revocation synchronized with Lycet.', $tester->getDisplay());
    }

    public function testUpsertRetryRequiresCertificateFile(): void
    {
        $service = $this->createMock(CertificateLifecycleServiceInterface::class);
        $service->expects(self::never())->method('retryUpsertSync');

        $tester = new CommandTester(new CertificateRetrySyncCommand($service));

        self::assertSame(Command::FAILURE, $tester->execute([
            'tenant' => 'acme',
            'ruc' => '20123456789',
            '--operation' => 'upsert',
            '--cert-file' => '/missing/cert.pem',
        ]));
        self::assertStringContainsString('Valid --cert-file is required for upsert retry.', $tester->getDisplay());
    }

    public function testRetriesUpsertWithCertificateFile(): void
    {
        $certFile = tempnam(sys_get_temp_dir(), 'retry-cert-');
        self::assertIsString($certFile);
        file_put_contents($certFile, 'certificate-pem');

        try {
            $service = $this->createMock(CertificateLifecycleServiceInterface::class);
            $service->expects(self::once())
                ->method('retryUpsertSync')
                ->with('acme', '20123456789', '20123456789MODDATOS', 'secret', 'certificate-pem')
                ->willReturn($this->certificate());

            $tester = new CommandTester(new CertificateRetrySyncCommand($service));

            $_SERVER['CERT_RETRY_SOL_PASS'] = 'secret';

            self::assertSame(Command::SUCCESS, $tester->execute([
                'tenant' => 'acme',
                'ruc' => '20123456789',
                '--operation' => 'upsert',
                '--sol-user' => '20123456789MODDATOS',
                '--sol-pass-env' => 'CERT_RETRY_SOL_PASS',
                '--cert-file' => $certFile,
            ]));
            self::assertStringContainsString('Certificate upsert synchronized with Lycet.', $tester->getDisplay());
        } finally {
            unset($_SERVER['CERT_RETRY_SOL_PASS']);
            @unlink($certFile);
        }
    }

    private function certificate(): TenantCertificate
    {
        return new TenantCertificate(
            id: 11,
            tenantId: 1,
            tenantName: 'acme',
            ruc: '20123456789',
            status: CertificateStatus::Active,
            subjectCn: 'Acme Cert',
            issuerCn: 'Acme Cert',
            serialNumber: '01',
            fingerprintSha256: str_repeat('a', 64),
            validFrom: new \DateTimeImmutable('2026-01-01'),
            expiresAt: new \DateTimeImmutable('2027-01-01'),
            uploadedAt: new \DateTimeImmutable('2026-04-24'),
            uploadedBy: 'ops@example.com',
            revokedAt: null,
            revokedBy: null,
            revocationReason: null,
            supersededById: null,
            lastAlertedAt: null,
        );
    }
}
