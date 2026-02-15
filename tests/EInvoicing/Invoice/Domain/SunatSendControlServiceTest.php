<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Invoice\Domain;

use App\Core\Service\SettingsService;
use App\Core\Service\TenantNotificationService;
use App\EInvoicing\Invoice\Domain\SunatSendControlService;
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
}
