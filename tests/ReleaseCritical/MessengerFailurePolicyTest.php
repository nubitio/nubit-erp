<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessengerFailurePolicyTest extends KernelTestCase
{
    public function testAsyncTransportHasBoundedRetriesAndDeadLetterTransport(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        static::assertInstanceOf(TransportInterface::class, $container->get('messenger.transport.async'));
        $failedTransport = $container->get('messenger.transport.failed');
        static::assertInstanceOf(TransportInterface::class, $failedTransport);
        $failureListener = $container->get('messenger.failure.send_failed_message_to_failure_transport_listener');
        static::assertInstanceOf(SendFailedMessageToFailureTransportListener::class, $failureListener);
        /** @var mixed $failureTransportsByName */
        $failureTransportsByName = new \ReflectionProperty($failureListener, 'failureTransportsByName')->getValue(
            $failureListener,
        );
        static::assertIsArray($failureTransportsByName);
        static::assertSame('failed', $failureTransportsByName['async'] ?? null);

        $retryStrategies = $container->get('messenger.retry_strategy_locator');
        static::assertInstanceOf(ContainerInterface::class, $retryStrategies);
        /** @var MultiplierRetryStrategy $strategy */
        $strategy = $retryStrategies->get('async');
        static::assertInstanceOf(MultiplierRetryStrategy::class, $strategy);

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
