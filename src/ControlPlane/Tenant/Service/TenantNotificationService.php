<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

readonly class TenantNotificationService
{
    public const string EVENT_SUNAT_PAUSED = 'sunat_paused';
    public const string EVENT_SUNAT_DAILY_SUMMARY = 'sunat_daily_summary';
    public const string EVENT_SUNAT_MAX_ATTEMPTS = 'sunat_max_attempts_blocked';

    private const array EVENT_DEFINITIONS = [
        self::EVENT_SUNAT_PAUSED => ['label' => 'SUNAT Paused', 'severity' => 'critical'],
        self::EVENT_SUNAT_DAILY_SUMMARY => ['label' => 'SUNAT Daily Summary', 'severity' => 'info'],
        self::EVENT_SUNAT_MAX_ATTEMPTS => ['label' => 'SUNAT Max Attempts Blocked', 'severity' => 'warning'],
    ];

    private const array CHANNELS = ['email', 'bird', 'webhook'];

    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom = 'noreply@nubit.pe',
    ) {}

    /**
     * @return array<string, array{label:string,severity:string}>
     */
    public function getEventDefinitions(): array
    {
        return self::EVENT_DEFINITIONS;
    }

    /**
     * @return list<string>
     */
    public function getSupportedChannels(): array
    {
        return self::CHANNELS;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getTenantTargets(int $tenantId): array
    {
        $cp = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($cp)) {
            return [];
        }

        $rows = $cp->fetchAllAssociative(
            'SELECT * FROM tenant_notification_targets WHERE tenant_id = ? ORDER BY event_key, channel',
            [$tenantId],
            [ParameterType::INTEGER],
        );

        foreach ($rows as &$row) {
            $row['recipients'] = $this->decodeJsonList($row['recipients_json'] ?? null);
            $row['config'] = $this->decodeJsonAssoc($row['config_json'] ?? null);
            $row['is_enabled'] = (int) ($row['is_enabled'] ?? 0) === 1;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, array<string, array{
     *   enabled:bool,
     *   severity:string,
     *   recipients:list<string>,
     *   config:array<string,mixed>
     * }>> $targetsByEvent
     */
    public function saveTenantTargets(int $tenantId, array $targetsByEvent): void
    {
        $cp = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($cp)) {
            throw new \RuntimeException('Control plane not ready for notifications.');
        }

        $cp->beginTransaction();
        try {
            foreach ($targetsByEvent as $eventKey => $channelMap) {
                if (!array_key_exists($eventKey, self::EVENT_DEFINITIONS)) {
                    continue;
                }

                foreach ($channelMap as $channel => $data) {
                    if (!in_array($channel, self::CHANNELS, true)) {
                        continue;
                    }

                    $enabled = !empty($data['enabled']) ? 1 : 0;
                    $severity = trim((string) $data['severity']);
                    $severity = in_array($severity, ['critical', 'warning', 'info'], true)
                        ? $severity
                        : $this->getEventDefinitions()[$eventKey]['severity'];
                    $recipients = $this->normalizeRecipients($data['recipients']);
                    $config = $data['config'];

                    $cp->executeStatement(
                        '
                        INSERT INTO tenant_notification_targets
                            (tenant_id, event_key, channel, severity, is_enabled, recipients_json, config_json)
                        VALUES
                            (:tenant_id, :event_key, :channel, :severity, :is_enabled, :recipients_json, :config_json)
                        ON DUPLICATE KEY UPDATE
                            severity = VALUES(severity),
                            is_enabled = VALUES(is_enabled),
                            recipients_json = VALUES(recipients_json),
                            config_json = VALUES(config_json)
                        ',
                        [
                            'tenant_id' => $tenantId,
                            'event_key' => $eventKey,
                            'channel' => $channel,
                            'severity' => $severity,
                            'is_enabled' => $enabled,
                            'recipients_json' => json_encode($recipients, JSON_UNESCAPED_UNICODE),
                            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
                        ],
                        [
                            'tenant_id' => ParameterType::INTEGER,
                            'event_key' => ParameterType::STRING,
                            'channel' => ParameterType::STRING,
                            'severity' => ParameterType::STRING,
                            'is_enabled' => ParameterType::INTEGER,
                            'recipients_json' => ParameterType::STRING,
                            'config_json' => ParameterType::STRING,
                        ],
                    );
                }
            }

            $cp->commit();
        } catch (Throwable $exception) {
            $cp->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function dispatchForTenantName(
        string $tenantName,
        string $eventKey,
        string $subject,
        string $message,
        array $payload = [],
        ?string $severity = null,
    ): int {
        $cp = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($cp)) {
            return 0;
        }

        $tenant = $cp->fetchAssociative('SELECT id, name FROM tenants WHERE name = ? LIMIT 1', [$tenantName]);
        if (!is_array($tenant)) {
            return 0;
        }

        $tenantId = (int) $tenant['id'];
        $targets = $cp->fetchAllAssociative(
            '
            SELECT *
            FROM tenant_notification_targets
            WHERE tenant_id = :tenant_id
              AND event_key = :event_key
              AND is_enabled = 1
            ',
            [
                'tenant_id' => $tenantId,
                'event_key' => $eventKey,
            ],
            [
                'tenant_id' => ParameterType::INTEGER,
                'event_key' => ParameterType::STRING,
            ],
        );

        if ($targets === []) {
            return 0;
        }

        $finalSeverity = $severity ?: self::EVENT_DEFINITIONS[$eventKey]['severity'] ?? 'info';
        $sent = 0;

        foreach ($targets as $target) {
            $channel = (string) ($target['channel'] ?? '');
            $targetSeverity = (string) ($target['severity'] ?? $finalSeverity);
            $recipients = $this->decodeJsonList($target['recipients_json'] ?? null);
            $config = $this->decodeJsonAssoc($target['config_json'] ?? null);

            try {
                $deliverySent = match ($channel) {
                    'email' => $this->sendEmail($subject, $message, $recipients),
                    'bird' => $this->sendBirdTemplate(
                        $targetSeverity,
                        $recipients,
                        $subject,
                        $message,
                        $payload,
                        $config,
                    ),
                    'webhook' => $this->sendWebhook(
                        $recipients,
                        $subject,
                        $message,
                        $payload,
                        $targetSeverity,
                        $tenantName,
                        $config,
                    ),
                    default => false,
                };

                $this->recordDelivery(
                    $tenantId,
                    $eventKey,
                    $channel,
                    $targetSeverity,
                    $recipients,
                    $payload + ['subject' => $subject, 'message' => $message],
                    $deliverySent ? 'sent' : 'skipped',
                    null,
                );

                if ($deliverySent) {
                    $sent++;
                }
            } catch (Throwable $exception) {
                $this->recordDelivery(
                    $tenantId,
                    $eventKey,
                    $channel,
                    $targetSeverity,
                    $recipients,
                    $payload + ['subject' => $subject, 'message' => $message],
                    'failed',
                    $exception->getMessage(),
                );
                $this->logger->error('Notification dispatch failed', [
                    'tenant' => $tenantName,
                    'tenant_id' => $tenantId,
                    'event_key' => $eventKey,
                    'channel' => $channel,
                    'exception' => $exception,
                ]);
            }
        }

        return $sent;
    }

    public function dispatchTestTargetByTenantId(
        int $tenantId,
        string $eventKey,
        string $channel,
        string $tenantName,
    ): bool {
        $cp = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($cp)) {
            return false;
        }

        if (!array_key_exists($eventKey, self::EVENT_DEFINITIONS)) {
            throw new \InvalidArgumentException(sprintf('Unsupported event key "%s"', $eventKey));
        }
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported channel "%s"', $channel));
        }

        $target = $cp->fetchAssociative(
            '
            SELECT *
            FROM tenant_notification_targets
            WHERE tenant_id = :tenant_id
              AND event_key = :event_key
              AND channel = :channel
            LIMIT 1
            ',
            [
                'tenant_id' => $tenantId,
                'event_key' => $eventKey,
                'channel' => $channel,
            ],
            [
                'tenant_id' => ParameterType::INTEGER,
                'event_key' => ParameterType::STRING,
                'channel' => ParameterType::STRING,
            ],
        );

        if (!is_array($target)) {
            return false;
        }

        if ((int) ($target['is_enabled'] ?? 0) !== 1) {
            return false;
        }

        $severity = (string) ($target['severity'] ?? $this->getEventDefinitions()[$eventKey]['severity']);
        $subject = sprintf('[TEST][%s] %s', strtoupper($channel), $this->getEventDefinitions()[$eventKey]['label']);
        $message = implode("\n", [
            'This is a test notification.',
            'Tenant: ' . $tenantName,
            'Event: ' . $eventKey,
            'Channel: ' . $channel,
            'Sent at: ' . date(DATE_ATOM),
        ]);
        $recipients = $this->decodeJsonList($target['recipients_json'] ?? null);
        $config = $this->decodeJsonAssoc($target['config_json'] ?? null);
        $payload = [
            'test' => true,
            'tenant_name' => $tenantName,
            'event_key' => $eventKey,
            'channel' => $channel,
            'severity' => $severity,
            'sent_at' => date(DATE_ATOM),
        ];

        try {
            $sent = false;
            if ($channel === 'email') {
                $sent = $this->sendEmail($subject, $message, $recipients);
            } elseif ($channel === 'bird') {
                $sent = $this->sendBirdTemplate($severity, $recipients, $subject, $message, $payload, $config);
            } elseif ($channel === 'webhook') {
                $sent = $this->sendWebhook($recipients, $subject, $message, $payload, $severity, $tenantName, $config);
            }

            $this->recordDelivery(
                $tenantId,
                $eventKey,
                $channel,
                $severity,
                $recipients,
                $payload + ['subject' => $subject, 'message' => $message],
                $sent ? 'sent' : 'skipped',
                null,
            );

            return $sent;
        } catch (Throwable $exception) {
            $this->recordDelivery(
                $tenantId,
                $eventKey,
                $channel,
                $severity,
                $recipients,
                $payload + ['subject' => $subject, 'message' => $message],
                'failed',
                $exception->getMessage(),
            );
            throw $exception;
        }
    }

    /**
     * @param list<string> $recipients
     */
    private function sendEmail(string $subject, string $message, array $recipients): bool
    {
        $emails = array_values(array_filter(
            $recipients,
            static fn(string $value): bool => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
        ));
        if ($emails === []) {
            return false;
        }

        $email = new Email()
            ->from($this->mailerFrom)
            ->subject($subject)
            ->text($message);

        foreach ($emails as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);

        return true;
    }

    /**
     * @param list<string> $recipients
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $config
     */
    private function sendBirdTemplate(
        string $severity,
        array $recipients,
        string $subject,
        string $message,
        array $payload,
        array $config,
    ): bool {
        $accessKey = (string) ($config['access_key'] ?? $_ENV['BIRD_ACCESS_KEY'] ?? '');
        $workspaceId = (string) ($config['workspace_id'] ?? $_ENV['BIRD_WORKSPACE_ID'] ?? '');
        $channelId = (string) ($config['channel_id'] ?? $_ENV['BIRD_CHANNEL_ID'] ?? '');
        $projectId = (string) ($config['project_id'] ?? $_ENV['BIRD_PROJECT_ID'] ?? '');
        $versionId = (string) ($config['version_id'] ?? $_ENV['BIRD_VERSION_ID'] ?? '');
        $locale = (string) ($config['locale'] ?? 'es');
        /** @var array<string, string> $parameterMap */
        $parameterMap = is_array($config['parameter_map'] ?? null) ? $config['parameter_map'] : [];

        if ($accessKey === '' || $workspaceId === '' || $channelId === '' || $projectId === '' || $versionId === '') {
            return false;
        }

        if ($recipients === []) {
            return false;
        }

        $parameters = [];
        if ($parameterMap !== []) {
            foreach ($parameterMap as $key => $payloadKey) {
                $parameters[] = [
                    'type' => 'string',
                    'key' => (string) $key,
                    'value' => (string) ($payload[$payloadKey] ?? ''),
                ];
            }
        } else {
            $parameters = [
                ['type' => 'string', 'key' => '1', 'value' => date('Y-m-d H:i:s')],
                ['type' => 'string', 'key' => '2', 'value' => $severity],
                ['type' => 'string', 'key' => '3', 'value' => $subject],
                ['type' => 'string', 'key' => '4', 'value' => mb_substr($message, 0, 250)],
            ];
        }

        $contacts = [];
        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if ($recipient === '') {
                continue;
            }

            $contacts[] = [
                'identifierValue' => $recipient,
                'identifierKey' => 'phonenumber',
            ];
        }

        if ($contacts === []) {
            return false;
        }

        $url = sprintf('https://api.bird.com/workspaces/%s/channels/%s/messages', $workspaceId, $channelId);
        $client = HttpClient::create();
        $response = $client->request('POST', $url, [
            'headers' => [
                'Authorization' => 'AccessKey ' . $accessKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'receiver' => ['contacts' => $contacts],
                'template' => [
                    'projectId' => $projectId,
                    'version' => $versionId,
                    'locale' => $locale,
                    'parameters' => $parameters,
                ],
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('Bird send failed with HTTP status ' . $code);
        }

        return true;
    }

    /**
     * @param list<string> $recipients
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $config
     */
    private function sendWebhook(
        array $recipients,
        string $subject,
        string $message,
        array $payload,
        string $severity,
        string $tenantName,
        array $config,
    ): bool {
        if ($recipients === []) {
            return false;
        }

        $headers = ['Content-Type' => 'application/json'];
        if (is_array($config['headers'] ?? null)) {
            foreach ($config['headers'] as $name => $value) {
                $headers[(string) $name] = (string) $value;
            }
        }

        $client = HttpClient::create();
        $sent = 0;
        foreach ($recipients as $url) {
            $url = trim($url);
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'json' => [
                    'tenant' => $tenantName,
                    'severity' => $severity,
                    'subject' => $subject,
                    'message' => $message,
                    'payload' => $payload,
                    'sent_at' => date(DATE_ATOM),
                ],
            ]);

            $code = $response->getStatusCode();
            if ($code >= 200 && $code < 300) {
                $sent++;
                continue;
            }

            throw new \RuntimeException(sprintf('Webhook %s failed with HTTP status %d', $url, $code));
        }

        return $sent > 0;
    }

    /**
     * @param list<string> $recipients
     * @param array<string,mixed> $payload
     */
    private function recordDelivery(
        int $tenantId,
        string $eventKey,
        string $channel,
        string $severity,
        array $recipients,
        array $payload,
        string $status,
        ?string $errorMessage,
    ): void {
        $cp = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($cp)) {
            return;
        }

        try {
            $cp->insert('tenant_notification_deliveries', [
                'tenant_id' => $tenantId,
                'event_key' => $eventKey,
                'channel' => $channel,
                'severity' => $severity,
                'status' => $status,
                'recipients_json' => json_encode($recipients, JSON_UNESCAPED_UNICODE),
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'error_message' => $errorMessage,
                'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Best effort logging only.
        }
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    private function isControlPlaneReady(Connection $connection): bool
    {
        try {
            return $connection->createSchemaManager()->tablesExist(['tenant_notification_targets', 'tenants']);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function decodeJsonList(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeRecipients($decoded);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonAssoc(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<int,mixed> $rawRecipients
     * @return list<string>
     */
    private function normalizeRecipients(array $rawRecipients): array
    {
        $normalized = [];
        foreach ($rawRecipients as $item) {
            if (!is_scalar($item) && $item !== null) {
                continue;
            }
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $normalized[$value] = true;
        }

        return array_keys($normalized);
    }
}
