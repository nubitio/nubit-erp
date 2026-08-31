<?php

declare(strict_types=1);

namespace App\Integration\Controller\Settings;

use App\Integration\Entity\ApiClient;
use App\Integration\Entity\ApiKey;
use App\Integration\Webhook\WebhookDelivery;
use App\Integration\Webhook\WebhookEndpoint;

/**
 * Shapes integration entities for the tenant settings UI. Never emits a
 * secret — those are returned inline once, by the create/rotate actions.
 */
final class IntegrationSettingsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function client(ApiClient $client): array
    {
        return [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'scopes' => $client->getScopes(),
            'created_at' => $client->getCreatedAt()->format(\DATE_ATOM),
            'keys' => array_map($this->key(...), $client->getKeys()->toArray()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function key(ApiKey $key): array
    {
        return [
            'id' => $key->getId(),
            'prefix' => $key->getPrefix(),
            'last_used_at' => $key->getLastUsedAt()?->format(\DATE_ATOM),
            'revoked_at' => $key->getRevokedAt()?->format(\DATE_ATOM),
            'created_at' => $key->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function endpoint(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'types' => $endpoint->getSubscribedTypes(),
            'status' => $endpoint->isEnabled() ? 'enabled' : 'disabled',
            'disabled_reason' => $endpoint->getDisabledReason(),
            'consecutive_failures' => $endpoint->getConsecutiveFailures(),
            'created_at' => $endpoint->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delivery(WebhookDelivery $delivery): array
    {
        return [
            'id' => $delivery->getId(),
            'event_id' => $delivery->getEventId(),
            'event_type' => $delivery->getEventType(),
            'status' => $delivery->getStatus(),
            'attempt' => $delivery->getAttempt(),
            'response_status' => $delivery->getResponseStatus(),
            'last_error' => $delivery->getLastError(),
            'next_retry_at' => $delivery->getNextRetryAt()?->format(\DATE_ATOM),
            'created_at' => $delivery->getCreatedAt()->format(\DATE_ATOM),
            'delivered_at' => $delivery->getDeliveredAt()?->format(\DATE_ATOM),
        ];
    }
}
