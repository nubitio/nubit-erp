<?php

declare(strict_types=1);

namespace App\Integration\Sales;

use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreditSaleLineInput
{
    /** @return array{product: string, qty: string, unitPrice: string} */
    public static function parse(mixed $line): array
    {
        if (!is_array($line) || array_diff(array_keys($line), ['product_id', 'quantity', 'unit_price']) !== []) {
            throw new UnprocessableEntityHttpException(
                'Each line must contain only product_id, quantity and unit_price.',
            );
        }
        $qty = self::decimal($line['quantity'] ?? null, '/\A[0-9]{1,6}(?:\.[0-9]{1,3})?\z/', 3);
        if (BigDecimal::of($qty)->isZero()) {
            throw new UnprocessableEntityHttpException('Line quantity must be positive.');
        }

        return [
            'product' => CreditSaleInput::identifier($line['product_id'] ?? null),
            'qty' => $qty,
            'unitPrice' => self::decimal($line['unit_price'] ?? null, '/\A[0-9]{1,8}(?:\.[0-9]{1,4})?\z/', 4),
        ];
    }

    private static function decimal(mixed $value, string $pattern, int $scale): string
    {
        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            throw new UnprocessableEntityHttpException(
                'Line quantity and unit price must be decimal strings at the supported precision.',
            );
        }

        return (string) BigDecimal::of($value)->toScale($scale);
    }
}
