<?php

declare(strict_types=1);

namespace App\Tests\Operations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class MessengerObservabilityConfigTest extends TestCase
{
    public function testTracingMiddlewareIsBoundToTheDefaultBus(): void
    {
        /** @var array<string, mixed> $config */
        $config = Yaml::parseFile(__DIR__ . '/../../config/services.yaml', Yaml::PARSE_CUSTOM_TAGS);
        /** @var array<string, mixed>|null $definition */
        $definition = $config['services']['Nubit\\Platform\\Messenger\\TracingMiddleware'] ?? null;

        self::assertIsArray($definition);
        self::assertSame('@nubit.observability.tracer', $definition['arguments']['$tracer'] ?? null);

        /** @var mixed $tags */
        $tags = $definition['tags'] ?? [];
        self::assertIsArray($tags);

        $defaultBusTags = array_filter(
            $tags,
            static fn(mixed $tag): bool => (
                is_array($tag)
                && ($tag['name'] ?? null) === 'messenger.middleware'
                && ($tag['bus'] ?? null) === 'messenger.bus.default'
            ),
        );

        self::assertNotSame([], $defaultBusTags);
    }
}
