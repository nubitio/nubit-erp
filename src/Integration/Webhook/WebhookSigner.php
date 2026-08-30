<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

/**
 * Stripe-style signatures. `Nb-Signature: t=<unix>,v1=<hex hmac-sha256>` over
 * `"<t>.<raw body>"`. The receiver recomputes, compares in constant time, and
 * rejects anything outside a small time window.
 */
final class WebhookSigner
{
    public const string HEADER = 'Nb-Signature';

    private const int TOLERANCE_SECONDS = 300;

    public function header(#[\SensitiveParameter] string $secret, string $body, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, $this->digest($secret, $body, $timestamp));
    }

    public function verify(#[\SensitiveParameter] string $secret, string $body, string $header, ?int $now = null): bool
    {
        $now ??= time();

        $parsed = $this->parse($header);
        if (null === $parsed) {
            return false;
        }
        [$timestamp, $signature] = $parsed;

        if (abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals($this->digest($secret, $body, $timestamp), $signature);
    }

    private function digest(#[\SensitiveParameter] string $secret, string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private function parse(string $header): ?array
    {
        $timestamp = null;
        $signature = null;
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (2 !== count($pair)) {
                continue;
            }
            if ('t' === $pair[0] && ctype_digit($pair[1])) {
                $timestamp = (int) $pair[1];
            } elseif ('v1' === $pair[0]) {
                $signature = $pair[1];
            }
        }

        return null !== $timestamp && null !== $signature ? [$timestamp, $signature] : null;
    }
}
