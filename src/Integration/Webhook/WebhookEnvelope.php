<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

use App\Integration\Event\IntegrationEvent;

/**
 * The JSON body of a webhook. Shape is part of the `v1` contract.
 */
final class WebhookEnvelope
{
    public const string API_VERSION = 'v1';

    /**
     * @return array<string, mixed>
     */
    public static function forEvent(IntegrationEvent $event): array
    {
        return [
            'id' => 'evt_' . (string) $event->getId(),
            'type' => $event->getType(),
            'api_version' => self::API_VERSION,
            'created_at' => $event->getOccurredAt()->format(\DATE_ATOM),
            'data' => $event->getPayload(),
        ];
    }
}
