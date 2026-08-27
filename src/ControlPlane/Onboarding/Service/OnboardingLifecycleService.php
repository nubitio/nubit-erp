<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final readonly class OnboardingLifecycleService
{
    public function __construct(
        private ConnectionRegistry $connections,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom = 'noreply@nubit.pe',
    ) {}

    public function sendReminders(): void
    {
        $this->sendAccountReminders();
        $this->sendTenantReminders();
    }

    /** @return list<int> */
    public function findArchivableTenantIds(int $days): array
    {
        return $this->safeTenantIds(
            "t.deleted_at IS NULL AND t.status = 'active' AND t.onboarding_reminder_stage >= 3",
            't.created_at',
            $days,
        );
    }

    /** @return list<int> */
    public function findPurgeableTenantIds(int $days): array
    {
        return $this->safeTenantIds("t.deleted_at IS NOT NULL AND t.status = 'deleted'", 't.deleted_at', $days);
    }

    /** @param list<int> $ids */
    public function archiveTenants(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $this->cp()->executeStatement(
            "UPDATE tenants SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id IN (?)",
            [$ids],
            [ArrayParameterType::INTEGER],
        );
    }

    /** @param list<int> $ids */
    public function purgeTenants(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $this->cp()->executeStatement('DELETE FROM tenants WHERE id IN (?)', [$ids], [ArrayParameterType::INTEGER]);
    }

    private function sendAccountReminders(): void
    {
        foreach ([3 => 168, 2 => 72, 1 => 24] as $stage => $ageHours) {
            $rows = $this->cp()->fetchAllAssociative('SELECT id, email FROM accounts WHERE verified_at IS NULL AND onboarding_reminder_stage < ? AND created_at <= ?', [
                $stage,
                $this->cutoff('hours', $ageHours),
            ]);
            foreach ($rows as $row) {
                if (!$this->send(
                    (string) $row['email'],
                    'Completa tu registro en Nubit ERP',
                    'onboarding/emails/account_reminder.html.twig',
                    [
                        'stage' => $stage,
                        'days_left' => max(0, 14 - intdiv(num1: $ageHours, num2: 24)),
                    ],
                )) {
                    continue;
                }
                $this->cp()->executeStatement('UPDATE accounts SET onboarding_reminder_stage = ?, onboarding_reminded_at = NOW() WHERE id = ?', [
                    $stage,
                    $row['id'],
                ]);
            }
        }
    }

    private function sendTenantReminders(): void
    {
        foreach ([3 => 14, 2 => 7, 1 => 3] as $stage => $ageDays) {
            $rows = $this->cp()->fetchAllAssociative(
                "SELECT DISTINCT t.id, t.name, a.email, d.domain
                 FROM tenants t INNER JOIN account_tenants at ON at.tenant_id = t.id
                 INNER JOIN accounts a ON a.id = at.account_id
                 LEFT JOIN tenant_domains d ON d.tenant_id = t.id AND d.is_primary = 1
                 WHERE t.deleted_at IS NULL AND t.status = 'active' AND t.plan = 'free'
                   AND t.onboarding_reminder_stage < ? AND t.created_at <= ?" . $this->safetySql(),
                [$stage, $this->cutoff('days', $ageDays)],
            );
            foreach ($rows as $row) {
                $domain = is_string($row['domain']) ? $row['domain'] : null;
                if (!$this->send(
                    (string) $row['email'],
                    'Continúa configurando tu empresa en Nubit ERP',
                    'onboarding/emails/tenant_reminder.html.twig',
                    [
                        'tenant_name' => (string) $row['name'],
                        'tenant_url' => $domain ? 'https://' . $domain . '/' : null,
                        'stage' => $stage,
                    ],
                )) {
                    continue;
                }
                $this->cp()->executeStatement(
                    'UPDATE tenants SET onboarding_reminder_stage = ?, onboarding_reminded_at = NOW(), abandonment_warned_at = IF(? = 3, NOW(), abandonment_warned_at) WHERE id = ?',
                    [$stage, $stage, $row['id']],
                );
            }
        }
    }

    /** @return list<int> */
    private function safeTenantIds(string $stateSql, string $dateColumn, int $days): array
    {
        $rows = $this->cp()->fetchFirstColumn(
            "SELECT t.id FROM tenants t WHERE {$stateSql} AND t.plan = 'free' AND {$dateColumn} <= ?"
                . $this->safetySql(),
            [$this->cutoff('days', $days)],
        );
        return array_map('intval', $rows);
    }

    private function safetySql(): string
    {
        return " AND NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.tenant_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM payment_events pe WHERE pe.tenant_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM tenant_usage u WHERE u.tenant_id = t.id AND u.metric_name IN ('customers','products','invoices','cash_shifts') AND u.metric_value > 0)";
    }

    /** @param array<string, mixed> $context */
    private function send(string $recipient, string $subject, string $template, array $context): bool
    {
        try {
            $this->mailer->send(
                new TemplatedEmail()
                    ->from($this->mailerFrom)
                    ->to($recipient)
                    ->subject($subject)
                    ->htmlTemplate($template)
                    ->context($context),
            );
            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send onboarding lifecycle email', [
                'email' => $recipient,
                'exception' => $exception,
            ]);
            return false;
        }
    }

    private function cutoff(string $unit, int $amount): string
    {
        return new \DateTimeImmutable(sprintf('-%d %s', $amount, $unit))->format('Y-m-d H:i:s');
    }

    private function cp(): Connection
    {
        $connection = $this->connections->getConnection('control_plane');
        if (!$connection instanceof Connection) {
            throw new \LogicException('The control-plane connection must be a DBAL connection.');
        }

        return $connection;
    }
}
