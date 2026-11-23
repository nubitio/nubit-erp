<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

/**
 * A tiny seeded generator so the demo story is identical on every nightly reset.
 *
 * Not for anything that needs real randomness: it is a linear congruential generator, chosen
 * because it gives the same sequence on every PHP version and platform.
 */
final class DemoRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0x7FFFFFFF;
    }

    /** A float in [0, 1). */
    public function next(): float
    {
        $this->state = (($this->state * 1_103_515_245) + 12_345) & 0x7FFFFFFF;

        return $this->state / 2_147_483_648;
    }

    /** An integer in [min, max], both included. */
    public function between(int $min, int $max): int
    {
        return $min + (int) floor($this->next() * ($max - $min + 1));
    }

    public function chance(float $probability): bool
    {
        return $this->next() < $probability;
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->between(0, count($items) - 1)];
    }

    /**
     * @param array<int|string, float|int> $weights
     *
     * @return string the chosen key
     */
    public function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $roll = $this->next() * $total;
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll < 0) {
                return (string) $key;
            }
        }

        return (string) array_key_last($weights);
    }
}
