<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Integration\Webhook\RetrySchedule;
use App\Integration\Webhook\WebhookSigner;
use PHPUnit\Framework\TestCase;

final class WebhookSignerAndScheduleTest extends TestCase
{
    public function testSignatureRoundTrips(): void
    {
        $signer = new WebhookSigner();
        $body = '{"id":"evt_1","type":"document.accepted"}';
        $now = 1_756_512_000;

        $header = $signer->header('whsec_abc', $body, $now);

        self::assertStringStartsWith('t=1756512000,v1=', $header);
        self::assertTrue($signer->verify('whsec_abc', $body, $header, $now));
    }

    public function testVerifyRejectsATamperedBody(): void
    {
        $signer = new WebhookSigner();
        $now = 1_756_512_000;
        $header = $signer->header('whsec_abc', 'original', $now);

        self::assertFalse($signer->verify('whsec_abc', 'tampered', $header, $now));
        self::assertFalse($signer->verify('whsec_wrong', 'original', $header, $now));
    }

    public function testVerifyRejectsStaleTimestamps(): void
    {
        $signer = new WebhookSigner();
        $header = $signer->header('whsec_abc', 'body', 1_000_000);

        self::assertFalse($signer->verify('whsec_abc', 'body', $header, 1_000_000 + 400));
        self::assertTrue($signer->verify('whsec_abc', 'body', $header, 1_000_000 + 200));
    }

    public function testVerifyRejectsMalformedHeaders(): void
    {
        $signer = new WebhookSigner();

        self::assertFalse($signer->verify('s', 'b', 'garbage'));
        self::assertFalse($signer->verify('s', 'b', 't=abc,v1=def'));
        self::assertFalse($signer->verify('s', 'b', 'v1=onlysig'));
    }

    public function testRetryScheduleBacksOffThenDeadLetters(): void
    {
        $schedule = new RetrySchedule();

        self::assertSame(60, $schedule->nextDelaySeconds(0));
        self::assertSame(300, $schedule->nextDelaySeconds(1));
        self::assertSame(86400, $schedule->nextDelaySeconds(7));
        self::assertNull($schedule->nextDelaySeconds(8), 'schedule is exhausted -> dead letter');

        $from = new \DateTimeImmutable('2026-08-30T00:00:00Z');
        self::assertEquals($from->modify('+60 seconds'), $schedule->nextRetryAt(0, $from));
        self::assertNull($schedule->nextRetryAt(99, $from));
        self::assertSame(9, $schedule->maxAttempts());
    }
}
