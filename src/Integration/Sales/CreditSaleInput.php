<?php

declare(strict_types=1);

namespace App\Integration\Sales;

use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class CreditSaleInput
{
    /** @param list<array{product: string, qty: string, unitPrice: string}> $items */
    private function __construct(
        public string $branchId,
        public string $warehouseId,
        public int $customerId,
        public array $items,
        public string $expectedTotal,
    ) {}

    /** @param array<array-key, mixed> $body */
    public static function fromArray(array $body): self
    {
        self::assertAllowedKeys($body, [
            'branch_id',
            'warehouse_id',
            'customer_id',
            'currency',
            'lines',
            'total_minor',
        ]);
        $customerId = $body['customer_id'] ?? null;
        $lines = $body['lines'] ?? null;
        $totalMinor = $body['total_minor'] ?? null;
        if (!is_int($customerId) || $customerId < 1 || $customerId > 2_147_483_647) {
            throw new UnprocessableEntityHttpException('customer_id must be a positive ERP customer ID.');
        }
        if (
            array_key_exists('currency', $body) && $body['currency'] !== 'PEN'
            || !is_int($totalMinor)
            || $totalMinor < 1
            || $totalMinor > 999_999_999_999
        ) {
            throw new UnprocessableEntityHttpException(
                'Only PEN credit sales with a positive integer total_minor are supported.',
            );
        }
        if (!is_array($lines) || !array_is_list($lines) || count($lines) < 1 || count($lines) > 100) {
            throw new UnprocessableEntityHttpException('lines must contain between 1 and 100 items.');
        }

        return new self(
            self::identifier($body['branch_id'] ?? null),
            self::identifier($body['warehouse_id'] ?? null),
            $customerId,
            array_map(CreditSaleLineInput::parse(...), $lines),
            (string) BigDecimal::of($totalMinor)->withPointMovedLeft(2)->toScale(2),
        );
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->branchId,
            $this->warehouseId,
            $this->customerId,
            $this->items,
            $this->expectedTotal,
        ], JSON_THROW_ON_ERROR));
    }

    public static function identifier(mixed $value): string
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new UnprocessableEntityHttpException('Branch, warehouse and product IDs must be UUIDs.');
        }

        return strtolower($value);
    }

    /**
     * @param array<array-key, mixed> $body
     * @param list<string> $allowed
     */
    private static function assertAllowedKeys(array $body, array $allowed): void
    {
        if (array_diff(array_keys($body), $allowed) !== []) {
            throw new UnprocessableEntityHttpException('The request contains fields not supported by credit sales.');
        }
    }
}
