<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Yaml\Yaml;

final class MessengerFailurePolicyTest extends TestCase
{
    public function testAsyncTransportHasBoundedRetriesAndDeadLetterTransport(): void
    {
        /** @var array{framework: array{messenger: array{failure_transport: string, transports: array{async: array{retry_strategy: array{max_retries: int}}, failed: string}}}} $parsed */
        $parsed = Yaml::parseFile(__DIR__ . '/../../config/packages/messenger.yaml');
        $config = $parsed['framework']['messenger'];

        static::assertSame('failed', $config['failure_transport']);
        static::assertSame(3, $config['transports']['async']['retry_strategy']['max_retries']);
        static::assertSame('doctrine://control_plane?queue_name=failed', $config['transports']['failed']);

        $strategy = new MultiplierRetryStrategy(3, 2000, 2);
        $message = new Envelope(new \stdClass());
        static::assertTrue($strategy->isRetryable($message));
        static::assertTrue($strategy->isRetryable($message->with(new RedeliveryStamp(2))));
        static::assertFalse($strategy->isRetryable($message->with(new RedeliveryStamp(3))));
        static::assertThat($strategy->getWaitingTime($message), static::greaterThanOrEqual(1800));
        static::assertThat($strategy->getWaitingTime($message), static::lessThanOrEqual(2200));
        static::assertThat(
            $strategy->getWaitingTime($message->with(new RedeliveryStamp(1))),
            static::greaterThanOrEqual(3600),
        );
        static::assertThat(
            $strategy->getWaitingTime($message->with(new RedeliveryStamp(1))),
            static::lessThanOrEqual(4400),
        );
    }
}
