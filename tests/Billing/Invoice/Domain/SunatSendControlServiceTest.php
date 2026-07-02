<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Domain;

use App\Billing\Invoice\Domain\SunatSendControlService;
use App\ControlPlane\Tenant\Service\TenantNotificationService;
use App\Settings\Entity\SunatOperationalState;
use App\Settings\Service\SunatStateService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SunatSendControlServiceTest extends TestCase
{
    private SunatStateService&MockObject $sunatState;
    private TenantNotificationService&MockObject $notificationService;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private SunatSendControlService $service;

    protected function setUp(): void
    {
        $this->sunatState = $this->createMock(SunatStateService::class);
        $this->notificationService = $this->createMock(TenantNotificationService::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SunatSendControlService(
            $this->sunatState,
            $this->notificationService,
            $this->mailer,
            $this->logger,
        );
    }

    // ── isSunatSendingEnabled ─────────────────────────────────────────────────

    public function testIsSunatSendingEnabledReturnsTrueWhenEnabled(): void
    {
        $this->sunatState->method('isSendEnabled')->willReturn(true);
        self::assertTrue($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledReturnsFalseWhenDisabled(): void
    {
        $this->sunatState->method('isSendEnabled')->willReturn(false);
        self::assertFalse($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledReturnsTrueOnException(): void
    {
        $this->sunatState->method('isSendEnabled')->willThrowException(new \RuntimeException('db'));
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to read SUNAT send flag, using enabled fallback', self::arrayHasKey('tenant'));

        self::assertTrue($this->service->isSunatSendingEnabled('tenant-a'));
    }

    // ── getRejectionThreshold ─────────────────────────────────────────────────

    public function testGetRejectionThresholdReturnsConfiguredValue(): void
    {
        $this->sunatState->method('getRejectionThreshold')->willReturn(5);
        self::assertSame(5, $this->service->getRejectionThreshold('tenant-a'));
    }

    public function testGetRejectionThresholdReturnsFallbackOnException(): void
    {
        $this->sunatState->method('getRejectionThreshold')->willThrowException(new \RuntimeException('db'));
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to read SUNAT rejection threshold, using fallback', self::arrayHasKey('tenant'));

        self::assertSame(3, $this->service->getRejectionThreshold('tenant-a'));
    }

    // ── incrementRejectionStreak ──────────────────────────────────────────────

    public function testIncrementRejectionStreakReturnsNewValue(): void
    {
        $this->sunatState->method('incrementRejectionStreak')->willReturn(3);
        self::assertSame(3, $this->service->incrementRejectionStreak('tenant-a'));
    }

    public function testIncrementRejectionStreakLogsErrorOnException(): void
    {
        $this->sunatState->method('incrementRejectionStreak')->willThrowException(new \RuntimeException('db'));
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to increment SUNAT rejection streak', self::arrayHasKey('tenant'));

        self::assertSame(1, $this->service->incrementRejectionStreak('tenant-a'));
    }

    // ── resetRejectionStreak ──────────────────────────────────────────────────

    public function testResetRejectionStreakDelegates(): void
    {
        $this->sunatState->expects(self::once())->method('resetRejectionStreak');
        $this->service->resetRejectionStreak('tenant-a');
    }

    public function testResetRejectionStreakLogsErrorOnException(): void
    {
        $this->sunatState->method('resetRejectionStreak')->willThrowException(new \RuntimeException('db'));
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to reset SUNAT rejection streak', self::arrayHasKey('tenant'));

        $this->service->resetRejectionStreak('tenant-a');
    }

    // ── isSunatRejection ──────────────────────────────────────────────────────

    public function testCode1032RequiresConsultAndIsNotRejection(): void
    {
        self::assertTrue($this->service->requiresConsult('1032', 'El comprobante ya esta informado'));
        self::assertFalse($this->service->isSunatRejection('1032', 'El comprobante ya esta informado'));
    }

    public function test2xxxCodeIsRejection(): void
    {
        self::assertTrue($this->service->isSunatRejection('2320', 'Rechazado'));
    }

    public function testCodeInRange2000to3999IsRejection(): void
    {
        self::assertTrue($this->service->isSunatRejection('2000', 'some error'));
        self::assertTrue($this->service->isSunatRejection('3999', 'some error'));
    }

    public function testNullCodeWithRechazMessageIsRejection(): void
    {
        self::assertTrue($this->service->isSunatRejection(null, 'Comprobante rechazado por SUNAT'));
    }

    // ── pauseSunatSendingAfterRejection ───────────────────────────────────────

    public function testPauseSunatSendingCallsPauseSendingAndDispatchesNotification(): void
    {
        $this->sunatState->expects(self::once())->method('pauseSending');
        $this->notificationService->expects(self::once())->method('dispatchForTenantName')->willReturn(1);
        $this->mailer->expects(self::never())->method('send');

        $this->service->pauseSunatSendingAfterRejection('doc-123', '2320', 'Rechazado', 3, 3, 'tenant-a');
    }

    public function testPauseSunatSendingLogsErrorWhenPauseFails(): void
    {
        $this->sunatState->method('pauseSending')->willThrowException(new \RuntimeException('db'));
        $this->notificationService->method('dispatchForTenantName')->willReturn(1);
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to persist SUNAT send pause flag', self::arrayHasKey('tenant'));

        $this->service->pauseSunatSendingAfterRejection('doc-err', '2320', 'Rechazado', 3, 3, 'tenant-a');
    }

    // ── isSunatRejection DataProvider ─────────────────────────────────────────

    #[DataProvider('rejectionCodeProvider')]
    public function testIsSunatRejectionWithVariousCodes(?string $code, string $message, bool $expected): void
    {
        self::assertSame($expected, $this->service->isSunatRejection($code, $message));
    }

    /** @return array<string, array{0: string|null, 1: string, 2: bool}> */
    public static function rejectionCodeProvider(): array
    {
        return [
            'code 2000 boundary' => ['2000', 'error', true],
            'code 3999 boundary' => ['3999', 'error', true],
            'code 4000 not rejection' => ['4000', 'error', false],
            'null code rechaz' => [null, 'Rechazado por SUNAT', true],
            'null code clean' => [null, 'Connection timeout', false],
            '1032 consult code' => ['1032', 'ya esta informado', false],
        ];
    }
}
