<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use App\Identity\Notification\NotificationType;
use App\Identity\Notification\UserNotificationServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Email;

final readonly class CertificateAlertService implements CertificateAlertServiceInterface
{
    private const int DEFAULT_DEDUP_SECONDS = 86400;

    public function __construct(
        private TenantCertificateRepositoryInterface $repository,
        private CertificateStatusPolicy $statusPolicy,
        private TenantConnectionSwitcherInterface $tenantConnectionSwitcher,
        private ConnectionRegistry $connectionRegistry,
        private UserNotificationServiceInterface $userNotificationService,
        private MailerInterface $mailer,
        private HubInterface $hub,
        private LoggerInterface $logger,
        private string $mailerFrom = 'noreply@efact.app',
    ) {
    }

    public function sendExpiringAlerts(int $days = 30): int
    {
        $sent = 0;
        foreach ($this->repository->findExpiringWithin($days) as $certificate) {
            if ($certificate->lastAlertedAt !== null && $certificate->lastAlertedAt > (new \DateTimeImmutable())->modify('-1 day')) {
                continue;
            }

            $status = $this->statusPolicy->statusFor($certificate->expiresAt);
            $this->repository->refreshStatus($certificate->id, $status);

            $title = sprintf('Certificado SUNAT por vencer: RUC %s', $certificate->ruc);
            $body = sprintf(
                'El certificado SUNAT del tenant %s vence el %s. Renueva el certificado para evitar fallas de facturación electrónica.',
                $certificate->tenantName,
                $certificate->expiresAt->format('Y-m-d')
            );
            $actionUrl = '/admin/tenants/' . $certificate->tenantId . '/certificates';

            try {
                $this->tenantConnectionSwitcher->switchConnection($certificate->tenantName);
                $recipients = $this->tenantAdminEmails();
                $this->sendEmails($recipients, $title, $body);
                $this->userNotificationService->notifyByRolesDeduped(
                    ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
                    NotificationType::CERTIFICATE_EXPIRING,
                    $status === CertificateStatus::Expired ? 'critical' : 'warning',
                    $title,
                    $body,
                    $actionUrl,
                    self::DEFAULT_DEDUP_SECONDS,
                );
                $this->publishMercure($certificate, $title, $body, $actionUrl);
                $this->repository->markAlerted($certificate->id);
                ++$sent;
            } catch (\Throwable $e) {
                $this->logger->error('Certificate expiration alert failed', [
                    'certificate_id' => $certificate->id,
                    'tenant' => $certificate->tenantName,
                    'ruc' => $certificate->ruc,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @return list<string>
     */
    private function tenantAdminEmails(): array
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        $rows = $connection->fetchAllAssociative(
            'SELECT username FROM usuario WHERE rol_id IN (1, 2) AND deleted_at IS NULL'
        );

        $emails = [];
        foreach ($rows as $row) {
            $email = trim((string)($row['username'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = mb_strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param list<string> $recipients
     */
    private function sendEmails(array $recipients, string $title, string $body): void
    {
        if ($recipients === []) {
            return;
        }

        $email = (new Email())
            ->from($this->mailerFrom)
            ->subject($title)
            ->text($body);

        foreach ($recipients as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);
    }

    private function publishMercure(TenantCertificate $certificate, string $title, string $body, string $actionUrl): void
    {
        $payload = [
            'type' => NotificationType::CERTIFICATE_EXPIRING,
            'tenant' => $certificate->tenantName,
            'ruc' => $certificate->ruc,
            'title' => $title,
            'body' => $body,
            'actionUrl' => $actionUrl,
        ];

        try {
            $this->hub->publish(new Update(
                sprintf('tenant/%s/notifications', $certificate->tenantName),
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Certificate Mercure alert publish failed', [
                'certificate_id' => $certificate->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
