<?php

declare(strict_types=1);

namespace App\Tests\Integration\Settings;

use App\Integration\Controller\Settings\IntegrationSettingsPresenter;
use App\Integration\Entity\ApiClient;
use App\Integration\Entity\ApiKey;
use App\Integration\Webhook\WebhookDelivery;
use App\Integration\Webhook\WebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class IntegrationSettingsPresenterTest extends TestCase
{
    private IntegrationSettingsPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new IntegrationSettingsPresenter();
    }

    public function testClientPayloadNeverCarriesASecretOrHash(): void
    {
        $client = new ApiClient('nubit-control', ['documents:write', 'documents:read']);
        new ApiKey($client, 'nb_live_demo_abcd1234', str_repeat('f', 64));

        $payload = $this->presenter->client($client);
        $flat = json_encode($payload, JSON_THROW_ON_ERROR);

        self::assertSame('nubit-control', $payload['name']);
        self::assertSame(['documents:write', 'documents:read'], $payload['scopes']);

        self::assertIsArray($payload['keys']);
        /** @var list<array<string, mixed>> $keys */
        $keys = $payload['keys'];
        self::assertCount(1, $keys);
        self::assertSame('nb_live_demo_abcd1234', $keys[0]['prefix']);
        self::assertStringNotContainsString(str_repeat('f', 64), $flat, 'the token hash must not be exposed');
        self::assertArrayNotHasKey('secret', $keys[0]);
        self::assertArrayNotHasKey('tokenHash', $keys[0]);
    }

    public function testEndpointPayloadNeverCarriesTheSigningSecret(): void
    {
        $endpoint = new WebhookEndpoint('https://x.test/hook', 'whsec_supersecret', ['document.*']);

        $payload = $this->presenter->endpoint($endpoint);

        self::assertSame('https://x.test/hook', $payload['url']);
        self::assertSame(['document.*'], $payload['types']);
        self::assertSame('enabled', $payload['status']);
        self::assertStringNotContainsString('whsec_supersecret', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testDeliveryPayloadShape(): void
    {
        $endpoint = new WebhookEndpoint('https://x.test/hook', 's', ['*']);
        $delivery = new WebhookDelivery($endpoint, 'evt-1', 'document.accepted', ['id' => 'evt_evt-1']);
        $delivery->failAndRetryAt(new \DateTimeImmutable('2026-08-30T12:00:00Z'), 500, 'boom');

        $payload = $this->presenter->delivery($delivery);

        self::assertSame('document.accepted', $payload['event_type']);
        self::assertSame('failed', $payload['status']);
        self::assertSame(1, $payload['attempt']);
        self::assertSame(500, $payload['response_status']);
        self::assertNotNull($payload['next_retry_at']);
    }
}
