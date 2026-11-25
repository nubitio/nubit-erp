<?php

declare(strict_types=1);

namespace App\Integration\Sales;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class ExternalBankPaymentInput
{
    private function __construct(
        public int $methodId,
        public string $amount,
        public string $currency,
        public DateTimeImmutable $paidAt,
        public string $reference,
    ) {}

    /** @param array<array-key, mixed> $body */
    public static function fromArray(array $body): self
    {
        $methodId = $body['payment_method_id'] ?? null;
        $amount = $body['amount_minor'] ?? null;
        $currency = $body['currency'] ?? null;
        $reference = $body['capture_reference'] ?? null;
        $timestamp = $body['paid_at'] ?? null;
        if (
            count($body) !== 5
            || !is_int($methodId)
            || $methodId < 1
            || $methodId > 2_147_483_647
            || !is_int($amount)
            || $amount < 1
            || $amount > 999_999_999_999
            || $currency !== 'PEN'
            || !is_string($reference)
            || preg_match('/\A[A-Za-z0-9._:-]{1,120}\z/', $reference) !== 1
            || !is_string($timestamp)
        ) {
            throw new UnprocessableEntityHttpException('Invalid confirmed PEN bank payment.');
        }
        $paidAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $timestamp, new DateTimeZone('UTC'));
        if ($paidAt === false || $paidAt->format('Y-m-d\TH:i:s\Z') !== $timestamp || $paidAt->format('Y') < '1000') {
            throw new UnprocessableEntityHttpException('paid_at must be a UTC timestamp with second precision.');
        }

        return new self(
            $methodId,
            (string) BigDecimal::of($amount)->withPointMovedLeft(2)->toScale(2),
            $currency,
            $paidAt,
            $reference,
        );
    }
}
