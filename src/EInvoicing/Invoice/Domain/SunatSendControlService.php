<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Domain;

use App\Core\Service\SettingsService;
use App\ControlPlane\Tenant\Service\TenantNotificationService;
use App\Core\Util\Constants;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

readonly class SunatSendControlService
{
    private const string ALERT_RECIPIENTS_KEY = 'app_alert_recipients';
    private const string ACCOUNTING_EMAIL_KEY = 'email_accounting';
    /**
     * SUNAT codes that must be resolved via CDR consult instead of counting as hard rejection.
     *
     * @var array<int, true>
     */
    private const CONSULT_REQUIRED_CODES = [
        1032 => true,
    ];

    public function __construct(
        private SettingsService $settings,
        private TenantNotificationService $notificationService,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function isSunatSendingEnabled(?string $tenantName): bool
    {
        try {
            $value = $this->settings->get(Constants::SUNAT_SEND_ENABLED, 'true');
        } catch (Throwable $exception) {
            $this->logger->error('Failed to read SUNAT send flag, using enabled fallback', [
                'tenant' => $tenantName,
                'exception' => $exception,
            ]);

            return true;
        }

        return filter_var((string)$value, FILTER_VALIDATE_BOOLEAN) !== false;
    }

    public function getRejectionThreshold(?string $tenantName): int
    {
        try {
            $raw = $this->settings->get(Constants::SUNAT_REJECTION_THRESHOLD, '3');
            $threshold = (int)$raw;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to read SUNAT rejection threshold, using fallback', [
                'tenant' => $tenantName,
                'exception' => $exception,
            ]);

            return 3;
        }

        return max(1, $threshold);
    }

    public function incrementRejectionStreak(?string $tenantName): int
    {
        $current = 0;
        try {
            $raw = $this->settings->get(Constants::SUNAT_REJECTION_STREAK, '0');
            $current = max(0, (int)$raw);
            $next = $current + 1;
            $this->settings->set(Constants::SUNAT_REJECTION_STREAK, 'int', (string)$next);

            return $next;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to increment SUNAT rejection streak', [
                'tenant' => $tenantName,
                'current_streak' => $current,
                'exception' => $exception,
            ]);
        }

        return $current + 1;
    }

    public function resetRejectionStreak(?string $tenantName): void
    {
        try {
            $raw = $this->settings->get(Constants::SUNAT_REJECTION_STREAK, '0');
            $current = max(0, (int)$raw);
            if ($current === 0) {
                return;
            }

            $this->settings->set(Constants::SUNAT_REJECTION_STREAK, 'int', '0');
        } catch (Throwable $exception) {
            $this->logger->error('Failed to reset SUNAT rejection streak', [
                'tenant' => $tenantName,
                'exception' => $exception,
            ]);
        }
    }

    public function isSunatRejection(?string $code, string $message): bool
    {
        if ($this->requiresConsult($code, $message)) {
            return false;
        }

        if ($code !== null && $code !== '' && ctype_digit($code)) {
            $numericCode = (int)$code;
            if ($numericCode >= 2000 && $numericCode < 4000) {
                return true;
            }
        }

        return stripos($message, 'rechaz') !== false;
    }

    public function requiresConsult(?string $code, string $message): bool
    {
        if ($code !== null && $code !== '' && ctype_digit($code)) {
            if (isset(self::CONSULT_REQUIRED_CODES[(int)$code])) {
                return true;
            }
        }

        return stripos($message, 'ya esta informado') !== false
            || stripos($message, 'ya está informado') !== false;
    }

    public function pauseSunatSendingAfterRejection(
        string $taxDocumentId,
        ?string $code,
        string $message,
        int $streak,
        int $threshold,
        ?string $tenantName,
    ): void {
        try {
            $this->settings->set(Constants::SUNAT_SEND_ENABLED, 'bool', 'false');
        } catch (Throwable $exception) {
            $this->logger->error('Failed to persist SUNAT send pause flag', [
                'tenant' => $tenantName,
                'tax_document_id' => $taxDocumentId,
                'sunat_code' => $code,
                'exception' => $exception,
            ]);
        }

        $tenant = $tenantName ?? 'default';
        $subject = sprintf('[ALERTA SUNAT] Envio pausado en tenant %s', $tenant);
        $payload = [
            'tenant_name' => $tenant,
            'streak' => $streak,
            'threshold' => $threshold,
            'tax_document_id' => $taxDocumentId,
            'sunat_code' => $code ?? 'N/A',
            'sunat_message' => $message,
            'flag' => Constants::SUNAT_SEND_ENABLED . '=false',
            'occurred_at' => date(DATE_ATOM),
        ];
        $body = implode("\n", [
            'Se detectaron rechazos SUNAT consecutivos y se pauso el envio automatico de comprobantes.',
            'Tenant: ' . $tenant,
            'Rechazos consecutivos: ' . $streak,
            'Umbral configurado: ' . $threshold,
            'Documento: ' . $taxDocumentId,
            'Codigo SUNAT: ' . ($code ?? 'N/A'),
            'Mensaje: ' . $message,
            'Flag: ' . Constants::SUNAT_SEND_ENABLED . '=false',
            'Accion: revisar causa y reactivar manualmente el flag al terminar la investigacion.',
        ]);

        $dispatched = $this->notificationService->dispatchForTenantName(
            tenantName: $tenant,
            eventKey: TenantNotificationService::EVENT_SUNAT_PAUSED,
            subject: $subject,
            message: $body,
            payload: $payload,
            severity: 'critical',
        );
        if ($dispatched > 0) {
            return;
        }

        $recipients = $this->getAlertRecipients($tenantName);
        if ($recipients === []) {
            $this->logger->warning('SUNAT sending paused but no alert recipients are configured', [
                'tenant' => $tenant,
                'tax_document_id' => $taxDocumentId,
                'sunat_code' => $code,
            ]);

            return;
        }

        $email = new Email()
            ->from('noreply@efact.app')
            ->subject($subject)
            ->text($body);

        foreach ($recipients as $recipient) {
            $email->addTo($recipient);
        }

        try {
            $this->mailer->send($email);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to send SUNAT rejection alert email', [
                'tenant' => $tenant,
                'tax_document_id' => $taxDocumentId,
                'sunat_code' => $code,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function getAlertRecipients(?string $tenantName): array
    {
        try {
            $rawRecipients = trim((string)($this->settings->get(self::ALERT_RECIPIENTS_KEY, '') ?? ''));
            $accountingEmail = trim((string)($this->settings->get(self::ACCOUNTING_EMAIL_KEY, '') ?? ''));
        } catch (Throwable $exception) {
            $this->logger->error('Failed to resolve SUNAT alert recipients', [
                'tenant' => $tenantName,
                'exception' => $exception,
            ]);

            return [];
        }

        $fromSetting = preg_split('/[;,]/', $rawRecipients) ?: [];
        $candidates = array_merge($fromSetting, [$accountingEmail]);
        $valid = [];

        foreach ($candidates as $candidate) {
            $email = trim((string)$candidate);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $valid[$email] = true;
        }

        return array_keys($valid);
    }
}
