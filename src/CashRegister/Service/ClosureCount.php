<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use App\CashRegister\Dto\CashRegisterCloseDto;
use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * What the cashier declared when closing: the cash in the drawer and the notes explaining the differences.
 *
 * Only cash is compared with what the drawer should hold. The other methods (Yape, a card...) are checked
 * against the terminal or the bank, and adding them here would turn every digital sale into a surplus.
 */
final readonly class ClosureCount
{
    /** The cash payment method; the one the drawer holds. */
    private const int CASH_PAYMENT_METHOD_ID = 1;

    /**
     * @return array{counted: BigDecimal, notes: list<string>}
     */
    public static function of(CashRegisterCloseDto $close): array
    {
        if ($close->closureItems === []) {
            return self::singleAmount($close);
        }

        $counted = BigDecimal::zero();
        $notes = [];
        foreach ($close->closureItems as $item) {
            $amount = self::amount($item['countedAmount'] ?? '0');
            // An item that names no method is a legacy one-line closure: it counts.
            if (!array_key_exists('paymentMethod', $item) || self::isCash($item['paymentMethod'])) {
                $counted = $counted->plus($amount);
            }
            $note = trim((string) ($item['note'] ?? ''));
            if ($note !== '') {
                $notes[] = $note;
            }
        }

        return ['counted' => $counted, 'notes' => $notes];
    }

    /**
     * @return array{counted: BigDecimal, notes: list<string>}
     */
    private static function singleAmount(CashRegisterCloseDto $close): array
    {
        $note = trim((string) ($close->note ?? ''));

        return [
            'counted' => self::amount($close->countedAmount ?? '0'),
            'notes' => $note === '' ? [] : [$note],
        ];
    }

    private static function amount(mixed $value): BigDecimal
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new UnprocessableEntityHttpException('El monto contado debe ser un número positivo.');
        }

        return BigDecimal::of((string) $value);
    }

    /** The item's `paymentMethod` is an IRI (`/api/payment-methods/1`) or a bare id. */
    private static function isCash(mixed $paymentMethod): bool
    {
        if (is_array($paymentMethod)) {
            $paymentMethod = $paymentMethod['id'] ?? null;
        }
        if (!is_string($paymentMethod) && !is_int($paymentMethod)) {
            return false;
        }

        return (int) basename((string) $paymentMethod) === self::CASH_PAYMENT_METHOD_ID;
    }
}
