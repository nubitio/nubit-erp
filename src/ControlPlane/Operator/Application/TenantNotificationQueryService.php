<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Application;

use App\ControlPlane\Tenant\Service\TenantNotificationService;

final readonly class TenantNotificationQueryService
{
    public function __construct(
        private TenantNotificationService $notificationService,
    ) {}

    /**
     * @return array{
     *   eventDefinitions: array<string, array{label: string, severity: string}>,
     *   channels: list<string>,
     *   matrix: array<string, array<string, array{
     *     enabled: bool,
     *     severity: string,
     *     recipients: list<string>,
     *     config: array<string, mixed>
     *   }>>
     * }
     */
    public function getMatrix(int $tenantId): array
    {
        $definitions = $this->notificationService->getEventDefinitions();
        $channels = $this->notificationService->getSupportedChannels();

        $matrix = [];
        foreach ($definitions as $eventKey => $def) {
            foreach ($channels as $channel) {
                $matrix[$eventKey][$channel] = [
                    'enabled' => false,
                    'severity' => (string) $def['severity'],
                    'recipients' => [],
                    'config' => [],
                ];
            }
        }

        foreach ($this->notificationService->getTenantTargets($tenantId) as $target) {
            $eventKey = (string) ($target['event_key'] ?? '');
            $channel = (string) ($target['channel'] ?? '');
            if (!isset($matrix[$eventKey][$channel])) {
                continue;
            }

            $matrix[$eventKey][$channel] = [
                'enabled' => (bool) ($target['is_enabled'] ?? false),
                'severity' => (string) ($target['severity'] ?? $definitions[$eventKey]['severity']),
                'recipients' => is_array($target['recipients'] ?? null)
                    ? array_values(array_filter($target['recipients'], is_string(...)))
                    : [],
                'config' => $this->readConfig($target['config'] ?? null),
            ];
        }

        return [
            'eventDefinitions' => $definitions,
            'channels' => $channels,
            'matrix' => $matrix,
        ];
    }

    /** @return array<string, mixed> */
    private function readConfig(mixed $value): array
    {
        $config = [];
        if (is_array($value)) {
            foreach ($value as $key => $entry) {
                if (is_string($key)) {
                    $config[$key] = $entry;
                }
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<string, array{
     *   enabled: bool,
     *   severity: string,
     *   recipients: list<string>,
     *   config: array<string, mixed>
     * }>>
     */
    public function parseTargetsPayload(array $payload): array
    {
        $definitions = $this->notificationService->getEventDefinitions();
        $channels = $this->notificationService->getSupportedChannels();
        $targets = [];

        /** @var array<string, array<string, mixed>> $matrix */
        $matrix = is_array($payload['matrix'] ?? null) ? $payload['matrix'] : $payload;

        foreach (array_keys($definitions) as $eventKey) {
            /** @var array<string, mixed> $channelMap */
            $channelMap = is_array($matrix[$eventKey] ?? null) ? $matrix[$eventKey] : [];

            foreach ($channels as $channel) {
                /** @var array<string, mixed> $row */
                $row = is_array($channelMap[$channel] ?? null) ? $channelMap[$channel] : [];

                $recipientsRaw = $row['recipients'] ?? [];
                if (is_string($recipientsRaw)) {
                    $recipients = $recipientsRaw === ''
                        ? []
                        : array_values(array_filter(array_map(
                            static fn(string $value): string => trim($value),
                            preg_split('/[\n,;]+/', $recipientsRaw) ?: [],
                        )));
                } elseif (is_array($recipientsRaw)) {
                    $recipients = array_values(array_filter(array_map(static fn(mixed $value): string => trim(
                        (string) $value,
                    ), $recipientsRaw)));
                } else {
                    $recipients = [];
                }

                $config = $row['config'] ?? [];
                if (is_string($config)) {
                    $decoded = json_decode($config, true);
                    if (!is_array($decoded)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Invalid JSON config for %s / %s.',
                            $eventKey,
                            $channel,
                        ));
                    }
                    $config = $decoded;
                }
                if (!is_array($config)) {
                    $config = [];
                }

                $targets[$eventKey][$channel] = [
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'severity' => trim((string) ($row['severity'] ?? $definitions[$eventKey]['severity'])),
                    'recipients' => $recipients,
                    'config' => $config,
                ];
            }
        }

        return $targets;
    }
}
