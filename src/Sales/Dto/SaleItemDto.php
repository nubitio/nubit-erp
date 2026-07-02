<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Override;
use Stringable;

class SaleItemDto implements Stringable
{
    public function __construct(
        private readonly int $lineWidth,
        private string $name = '',
        private readonly float|string|null $price = '',
        private readonly float|string|null $total = null,
    ) {}

    #[Override]
    public function __toString(): string
    {
        $line_width = $this->lineWidth;
        $rightCols = (int) round((20 / 100) * $line_width);
        $centerCols = (int) round((20 / 100) * $line_width);
        $leftCols = $line_width - $rightCols - $centerCols;

        $this->name = strlen($this->name) > $leftCols ? substr($this->name, 0, $leftCols) : $this->name;
        $left = str_pad($this->name, $leftCols);

        $formatMoney = function ($value): string {
            if ($value === null || $value === '') {
                return '';
            }
            if (is_numeric($value)) {
                return number_format((float) $value, 2, '.', '');
            }
            return (string) $value;
        };

        $price = $formatMoney($this->price);
        $total = $formatMoney($this->total ?? $this->price);

        $center = str_pad($price, $centerCols, ' ', STR_PAD_LEFT);
        $right = str_pad($total, $rightCols, ' ', STR_PAD_LEFT);

        return $left . $center . $right . PHP_EOL;
    }
}
