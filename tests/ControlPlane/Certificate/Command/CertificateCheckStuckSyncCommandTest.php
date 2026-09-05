<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\Command\CertificateCheckStuckSyncCommand;
use App\ControlPlane\Certificate\TenantCertificate;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CertificateCheckStuckSyncCommandTest extends TestCase
{
    public function testSucceedsAndLogsNothingWhenNoCertificateIsStuck(): void
    {
        $certificates = $this->createMock(CertificateLifecycleServiceInterface::class);
        $certificates->expects(self::once())->method('findStuckLycetSyncs')->with(30)->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $tester = new CommandTester(new CertificateCheckStuckSyncCommand($certificates, $logger));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testFailsAndLogsEachStuckCertificate(): void
    {
        $stuck = new TenantCertificate(
            id: 42,
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
            lycetSyncStatus: 'upsert_failed',
            lycetSyncError: 'Lycet returned 500',
            lycetSyncUpdatedAt: new \DateTimeImmutable('-2 hours'),
        );

        $certificates = $this->createMock(CertificateLifecycleServiceInterface::class);
        $certificates->expects(self::once())->method('findStuckLycetSyncs')->with(45)->willReturn([$stuck]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('stuck'),
                self::callback(static function (array $context): bool {
                    self::assertSame('acme', $context['tenantName']);
                    self::assertSame('upsert_failed', $context['lycetSyncStatus']);

                    return true;
                }),
            );

        $tester = new CommandTester(new CertificateCheckStuckSyncCommand($certificates, $logger));
        $exitCode = $tester->execute(['--minutes' => '45']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('acme', $tester->getDisplay());
        self::assertStringContainsString('upsert_failed', $tester->getDisplay());
    }

    /**
     * @param non-empty-string $invalidValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMinutesProvider')]
    public function testRejectsInvalidMinutesOption(string $invalidValue): void
    {
        $certificates = $this->createMock(CertificateLifecycleServiceInterface::class);
        $certificates->expects(self::never())->method('findStuckLycetSyncs');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $tester = new CommandTester(new CertificateCheckStuckSyncCommand($certificates, $logger));
        $exitCode = $tester->execute(['--minutes' => $invalidValue]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function invalidMinutesProvider(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'non-numeric' => ['abc'];
        yield 'decimal' => ['1.5'];
    }
}
