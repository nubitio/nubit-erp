<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTimeImmutable;

/** Builds an ordinary walk-in sale: what is in the basket, how it is paid, any discount, and whether it is undone. */
final class BasketFactory
{
    private const array VOID_REASONS = [
        'Error de cobro',
        'Cliente se arrepintió',
        'Producto equivocado',
        'Cobro duplicado',
    ];

    private const array DRIFT_DISCOUNT_REASONS = [
        'Cliente frecuente',
        'Descuento por volumen',
        'Producto cerca de vencer',
        'Autorizado',
    ];

    /** @var array<string, int> product code => basket weight */
    private array $weights = [];
    /** @var array<string, int> product code => highest quantity in one line */
    private array $maxQty = [];

    public function __construct(
        private readonly DemoRandom $random,
    ) {
        foreach (MinimarketCatalog::products() as $product) {
            if ($product['weight'] > 0) {
                $this->weights[$product['code']] = $product['weight'];
            }
            $this->maxQty[$product['code']] = $product['maxQty'];
        }
    }

    /**
     * @param bool $drifting true for the weeks in which this cashier voids and discounts far more than usual
     */
    public function walkIn(string $cashier, DateTimeImmutable $at, bool $drifting): SalePlan
    {
        [$discount, $discountReason] = $this->discount($cashier, $drifting);
        [$outcome, $outcomeReason, $outcomeAt] = $this->outcome($at, $drifting);

        return new SalePlan(
            cashier: $cashier,
            at: $at,
            customerKey: null,
            lines: $this->lines(),
            payment: $this->random->weighted([
                'Efectivo' => 55,
                'Yape' => 22,
                'Plin' => 8,
                'Tarjeta débito' => 8,
                'Transferencia' => 2,
            ]),
            discountPercent: $discount,
            discountReason: $discountReason,
            outcome: $outcome,
            outcomeReason: $outcomeReason,
            outcomeAt: $outcomeAt,
        );
    }

    /** @return list<array{code: string, qty: int}> */
    private function lines(): array
    {
        $lineCount = (int) $this->random->weighted(['1' => 30, '2' => 30, '3' => 20, '4' => 12, '5' => 8]);
        $lines = [];
        for ($i = 0; $i < $lineCount; ++$i) {
            $code = $this->random->weighted($this->weights);
            $lines[$code] = ['code' => $code, 'qty' => $this->random->between(1, $this->maxQty[$code])];
        }

        return array_values($lines);
    }

    /** @return array{0: float, 1: string|null} */
    private function discount(string $cashier, bool $drifting): array
    {
        if ($drifting) {
            return (
                $this->random->chance(0.42)
                    ? [(float) $this->random->pick([10, 12.5, 15]), $this->random->pick(self::DRIFT_DISCOUNT_REASONS)]
                    : [0.0, null]
            );
        }

        return $this->random->chance($cashier === 'maria' ? 0.04 : 0.03) ? [5.0, 'Cliente frecuente'] : [0.0, null];
    }

    /** @return array{0: SalePlan::NORMAL|SalePlan::VOIDED, 1: string|null, 2: DateTimeImmutable|null} */
    private function outcome(DateTimeImmutable $at, bool $drifting): array
    {
        if ($drifting && $this->random->chance(0.12)) {
            return [
                SalePlan::VOIDED,
                $this->random->pick(self::VOID_REASONS),
                $at->modify(sprintf('+%d minutes', $this->random->between(3, 25))),
            ];
        }
        if (!$drifting && $this->random->chance(0.004)) {
            return [
                SalePlan::VOIDED,
                'Error de cobro',
                $at->modify(sprintf('+%d minutes', $this->random->between(2, 10))),
            ];
        }

        return [SalePlan::NORMAL, null, null];
    }
}
