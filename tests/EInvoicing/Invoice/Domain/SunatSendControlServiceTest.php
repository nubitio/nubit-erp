<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Invoice\Domain;

use App\Core\Service\SettingsService;
use App\Core\Service\TenantNotificationService;
use App\Core\Util\Constants;
use App\EInvoicing\Invoice\Domain\SunatSendControlService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class SunatSendControlServiceTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private TenantNotificationService&MockObject $notificationService;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private SunatSendControlService $service;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsService::class);
        $this->notificationService = $this->createMock(TenantNotificationService::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SunatSendControlService(
            $this->settings,
            $this->notificationService,
            $this->mailer,
            $this->logger
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // isSunatSendingEnabled
    // ──────────────────────────────────────────────────────────────────────────

    public function testIsSunatSendingEnabledReturnsTrueWhenFlagIsTrue(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_SEND_ENABLED, 'true')
            ->willReturn('true');

        self::assertTrue($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledReturnsTrueWhenFlagIs1(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_SEND_ENABLED, 'true')
            ->willReturn('1');

        self::assertTrue($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledReturnsFalseWhenFlagIsFalse(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_SEND_ENABLED, 'true')
            ->willReturn('false');

        self::assertFalse($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledReturnsFalseWhenFlagIsZero(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_SEND_ENABLED, 'true')
            ->willReturn('0');

        self::assertFalse($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledReturnsTrueOnSettingsException(): void
    {
        $this->settings->method('get')
            ->willThrowException(new \RuntimeException('db error'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to read SUNAT send flag, using enabled fallback',
                self::arrayHasKey('tenant')
            );

        self::assertTrue($this->service->isSunatSendingEnabled('tenant-a'));
    }

    public function testIsSunatSendingEnabledWithNullTenantFallsBackToEnabledOnException(): void
    {
        $this->settings->method('get')
            ->willThrowException(new \RuntimeException('db error'));

        // Should still return true (safe default) even with null tenant
        self::assertTrue($this->service->isSunatSendingEnabled(null));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getRejectionThreshold
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetRejectionThresholdReturnsConfiguredValue(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_THRESHOLD, '3')
            ->willReturn('5');

        self::assertSame(5, $this->service->getRejectionThreshold('tenant-a'));
    }

    public function testGetRejectionThresholdClampsToMinimumOne(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_THRESHOLD, '3')
            ->willReturn('0');

        self::assertSame(1, $this->service->getRejectionThreshold('tenant-a'));
    }

    public function testGetRejectionThresholdClampsNegativeToOne(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_THRESHOLD, '3')
            ->willReturn('-5');

        self::assertSame(1, $this->service->getRejectionThreshold('tenant-a'));
    }

    public function testGetRejectionThresholdReturnsFallbackOnException(): void
    {
        $this->settings->method('get')
            ->willThrowException(new \RuntimeException('db error'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to read SUNAT rejection threshold, using fallback',
                self::arrayHasKey('tenant')
            );

        self::assertSame(3, $this->service->getRejectionThreshold('tenant-a'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // incrementRejectionStreak
    // ──────────────────────────────────────────────────────────────────────────

    public function testIncrementRejectionStreakIncrementsFromZero(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('0');
        $this->settings->expects(self::once())
            ->method('set')
            ->with(Constants::SUNAT_REJECTION_STREAK, 'int', '1');

        $result = $this->service->incrementRejectionStreak('tenant-a');

        self::assertSame(1, $result);
    }

    public function testIncrementRejectionStreakIncrementsFromExistingValue(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('2');
        $this->settings->expects(self::once())
            ->method('set')
            ->with(Constants::SUNAT_REJECTION_STREAK, 'int', '3');

        $result = $this->service->incrementRejectionStreak('tenant-a');

        self::assertSame(3, $result);
    }

    public function testIncrementRejectionStreakClampsNegativeStoredValueToZero(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('-10');
        $this->settings->expects(self::once())
            ->method('set')
            ->with(Constants::SUNAT_REJECTION_STREAK, 'int', '1');

        $result = $this->service->incrementRejectionStreak('tenant-a');

        self::assertSame(1, $result);
    }

    public function testIncrementRejectionStreakLogsErrorAndReturnsFallbackOnException(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willThrowException(new \RuntimeException('db error'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to increment SUNAT rejection streak',
                self::arrayHasKey('tenant')
            );

        // current=0 on exception, returns 0+1=1
        $result = $this->service->incrementRejectionStreak('tenant-a');

        self::assertSame(1, $result);
    }

    public function testIncrementRejectionStreakLogsErrorOnSetException(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('2');
        $this->settings->method('set')
            ->willThrowException(new \RuntimeException('db write error'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to increment SUNAT rejection streak',
                self::arrayHasKey('current_streak')
            );

        // Returns current(2) + 1 = 3 even on exception
        $result = $this->service->incrementRejectionStreak('tenant-a');

        self::assertSame(3, $result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // resetRejectionStreak
    // ──────────────────────────────────────────────────────────────────────────

    public function testResetRejectionStreakSetsCountToZeroWhenPositive(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('3');
        $this->settings->expects(self::once())
            ->method('set')
            ->with(Constants::SUNAT_REJECTION_STREAK, 'int', '0');

        $this->service->resetRejectionStreak('tenant-a');
    }

    public function testResetRejectionStreakDoesNothingWhenAlreadyZero(): void
    {
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('0');
        $this->settings->expects(self::never())
            ->method('set');

        $this->service->resetRejectionStreak('tenant-a');
    }

    public function testResetRejectionStreakDoesNothingWhenNegativeStored(): void
    {
        // Negative is clamped to 0 → also treated as already zero → no-op
        $this->settings->method('get')
            ->with(Constants::SUNAT_REJECTION_STREAK, '0')
            ->willReturn('-1');
        $this->settings->expects(self::never())
            ->method('set');

        $this->service->resetRejectionStreak('tenant-a');
    }

    public function testResetRejectionStreakLogsErrorOnException(): void
    {
        $this->settings->method('get')
            ->willThrowException(new \RuntimeException('db error'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to reset SUNAT rejection streak',
                self::arrayHasKey('tenant')
            );

        // Should not throw
        $this->service->resetRejectionStreak('tenant-a');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // isSunatRejection
    // ──────────────────────────────────────────────────────────────────────────

    public function testCode1032RequiresConsultAndIsNotRejection(): void
    {
        self::assertTrue($this->service->requiresConsult('1032', 'El comprobante ya esta informado'));
        self::assertFalse($this->service->isSunatRejection('1032', 'El comprobante ya esta informado'));
    }

    public function testAlreadyReportedMessageRequiresConsultWithoutCode(): void
    {
        self::assertTrue($this->service->requiresConsult(null, 'ya está informado y se encuentra con estado anulado'));
        self::assertFalse($this->service->isSunatRejection(null, 'ya está informado y se encuentra con estado anulado'));
    }

    public function test2xxxCodeIsRejectionWhenNotConsultable(): void
    {
        self::assertFalse($this->service->requiresConsult('2320', 'Rechazado'));
        self::assertTrue($this->service->isSunatRejection('2320', 'Rechazado'));
    }

    public function testCodeInRange2000to3999IsRejection(): void
    {
        self::assertTrue($this->service->isSunatRejection('2000', 'some error'));
        self::assertTrue($this->service->isSunatRejection('3999', 'some error'));
    }

    public function testCodeExactly4000IsNotRejection(): void
    {
        self::assertFalse($this->service->isSunatRejection('4000', 'some error'));
    }

    public function testCodeBelow2000IsNotRejection(): void
    {
        // message must not contain "rechaz" to avoid the fallback matching
        self::assertFalse($this->service->isSunatRejection('0033', 'Error de transporte'));
        self::assertFalse($this->service->isSunatRejection('1999', 'Connection timeout'));
    }

    public function testNullCodeWithRechazMessageIsRejection(): void
    {
        self::assertTrue($this->service->isSunatRejection(null, 'Comprobante rechazado por SUNAT'));
    }

    public function testNullCodeWithEmptyMessageIsNotRejection(): void
    {
        self::assertFalse($this->service->isSunatRejection(null, 'some generic error'));
    }

    public function testEmptyCodeWithRechazMessageIsRejection(): void
    {
        self::assertTrue($this->service->isSunatRejection('', 'documento rechazado'));
    }

    public function testNonNumericCodeFallsBackToMessageCheck(): void
    {
        self::assertFalse($this->service->isSunatRejection('ABC', 'Connection timeout'));
        self::assertTrue($this->service->isSunatRejection('ABC', 'fue rechazado por SUNAT'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // requiresConsult
    // ──────────────────────────────────────────────────────────────────────────

    public function testRequiresConsultForAlreadyInformedVariantWithoutAccent(): void
    {
        self::assertTrue($this->service->requiresConsult(null, 'el documento ya esta informado'));
    }

    public function testRequiresConsultForAlreadyInformedVariantWithAccent(): void
    {
        self::assertTrue($this->service->requiresConsult(null, 'ya está informado en el sistema'));
    }

    public function testRequiresConsultIsFalseForUnrelatedCode(): void
    {
        self::assertFalse($this->service->requiresConsult('2320', 'Error de validacion'));
    }

    public function testRequiresConsultIsFalseForNullCodeAndUnrelatedMessage(): void
    {
        self::assertFalse($this->service->requiresConsult(null, 'Error de validacion'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // pauseSunatSendingAfterRejection
    // ──────────────────────────────────────────────────────────────────────────

    public function testPauseSunatSendingSetsFlagAndDispatchesNotification(): void
    {
        $this->settings->expects(self::once())
            ->method('set')
            ->with(Constants::SUNAT_SEND_ENABLED, 'bool', 'false');

        $this->notificationService->expects(self::once())
            ->method('dispatchForTenantName')
            ->with(
                'tenant-a',
                TenantNotificationService::EVENT_SUNAT_PAUSED,
                self::stringContains('tenant-a'),
                self::isString(),
                self::isArray(),
                'critical',
            )
            ->willReturn(1);

        // When notification is dispatched, mailer should NOT be called
        $this->mailer->expects(self::never())->method('send');

        $this->service->pauseSunatSendingAfterRejection(
            'doc-123',
            '2320',
            'Rechazado por SUNAT',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingFallsBackToEmailWhenNotificationNotDispatched(): void
    {
        $this->settings->method('set');

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(0);

        // Recipients configured
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::SUNAT_SEND_ENABLED, 'true', 'true'],
                ['app_alert_recipients', '', 'admin@example.com'],
                ['email_accounting', '', 'cfo@example.com'],
            ]);

        $this->mailer->expects(self::once())->method('send');

        $this->service->pauseSunatSendingAfterRejection(
            'doc-456',
            '2500',
            'Rechazo critico',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingLogsWarningWhenNoRecipientsAndNotificationNotDispatched(): void
    {
        $this->settings->method('set');

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(0);

        // No recipients in settings
        $this->settings->method('get')
            ->willReturnMap([
                ['app_alert_recipients', '', ''],
                ['email_accounting', '', ''],
            ]);

        $this->mailer->expects(self::never())->method('send');

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'SUNAT sending paused but no alert recipients are configured',
                self::arrayHasKey('tenant')
            );

        $this->service->pauseSunatSendingAfterRejection(
            'doc-789',
            '3000',
            'Rechazado',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingLogsErrorWhenSettingsSetFails(): void
    {
        $this->settings->expects(self::atLeastOnce())
            ->method('set')
            ->willThrowException(new \RuntimeException('db write error'));

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(1);

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to persist SUNAT send pause flag',
                self::arrayHasKey('tenant')
            );

        // Should not throw even when settings.set fails
        $this->service->pauseSunatSendingAfterRejection(
            'doc-err',
            '2320',
            'Rechazado',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingUsesDefaultTenantWhenNullProvided(): void
    {
        $this->settings->method('set');

        $this->notificationService->expects(self::once())
            ->method('dispatchForTenantName')
            ->with(
                'default',
                TenantNotificationService::EVENT_SUNAT_PAUSED,
                self::stringContains('default'),
                self::isString(),
                self::isArray(),
                'critical',
            )
            ->willReturn(1);

        $this->service->pauseSunatSendingAfterRejection(
            'doc-null',
            null,
            'Rechazado',
            2,
            3,
            null
        );
    }

    public function testPauseSunatSendingLogsErrorWhenMailerThrows(): void
    {
        $this->settings->method('set');

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(0);

        // Valid recipients so mailer is called
        $this->settings->method('get')
            ->willReturnMap([
                ['app_alert_recipients', '', 'admin@example.com'],
                ['email_accounting', '', ''],
            ]);

        $this->mailer->method('send')
            ->willThrowException(new \RuntimeException('SMTP error'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send SUNAT rejection alert email',
                self::arrayHasKey('tenant')
            );

        // Should not throw
        $this->service->pauseSunatSendingAfterRejection(
            'doc-smtp',
            '2100',
            'Rechazado',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingLogsErrorWhenGetAlertRecipientsFails(): void
    {
        $this->settings->expects(self::once())
            ->method('set')
            ->with(Constants::SUNAT_SEND_ENABLED, 'bool', 'false');

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(0);

        // settings->get throws for the recipient lookups
        $this->settings->method('get')
            ->willThrowException(new \RuntimeException('db error'));

        $this->mailer->expects(self::never())->method('send');

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to resolve SUNAT alert recipients',
                self::arrayHasKey('tenant')
            );

        $this->service->pauseSunatSendingAfterRejection(
            'doc-recip',
            '2200',
            'Rechazado',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingDeduplicatesEmailRecipients(): void
    {
        $this->settings->method('set');

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(0);

        // Same address appears in both settings — should send only once
        $this->settings->method('get')
            ->willReturnMap([
                ['app_alert_recipients', '', 'admin@example.com;admin@example.com'],
                ['email_accounting', '', 'admin@example.com'],
            ]);

        // The email should still be sent (one unique recipient)
        $this->mailer->expects(self::once())->method('send');

        $this->service->pauseSunatSendingAfterRejection(
            'doc-dedup',
            '2300',
            'Rechazado',
            3,
            3,
            'tenant-a'
        );
    }

    public function testPauseSunatSendingIgnoresInvalidEmailRecipients(): void
    {
        $this->settings->method('set');

        $this->notificationService->method('dispatchForTenantName')
            ->willReturn(0);

        // All invalid → empty recipients → no email → log warning
        $this->settings->method('get')
            ->willReturnMap([
                ['app_alert_recipients', '', 'not-an-email, another-invalid'],
                ['email_accounting', '', 'also-not-an-email'],
            ]);

        $this->mailer->expects(self::never())->method('send');

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'SUNAT sending paused but no alert recipients are configured',
                self::arrayHasKey('tenant')
            );

        $this->service->pauseSunatSendingAfterRejection(
            'doc-invalid-email',
            '2300',
            'Rechazado',
            3,
            3,
            'tenant-a'
        );
    }

    /**
     * @param string|null $code
     * @param string      $message
     * @param bool        $expectedRejection
     */
    #[DataProvider('rejectionCodeProvider')]
    public function testIsSunatRejectionWithVariousCodes(?string $code, string $message, bool $expectedRejection): void
    {
        self::assertSame($expectedRejection, $this->service->isSunatRejection($code, $message));
    }

    /**
     * @return array<string, array{0: string|null, 1: string, 2: bool}>
     */
    public static function rejectionCodeProvider(): array
    {
        return [
            'code 2000 boundary'         => ['2000', 'error', true],
            'code 3999 boundary'         => ['3999', 'error', true],
            'code 2500 rejection'        => ['2500', 'error', true],
            'code 0152 no rejection'     => ['0152', 'error', false],
            'code 4000 not rejection'    => ['4000', 'error', false],
            'code 5000 not rejection'    => ['5000', 'error', false],
            'null code rechaz message'   => [null, 'Rechazado por SUNAT', true],
            'null code clean message'    => [null, 'Connection timeout', false],
            'empty code rechaz message'  => ['', 'rechazado', true],
            'non-numeric code no rechaz' => ['ERR', 'Transport error', false],
            'non-numeric code rechaz'    => ['ERR', 'fue rechazado', true],
            '1032 consult code'          => ['1032', 'ya esta informado', false],
        ];
    }
}
