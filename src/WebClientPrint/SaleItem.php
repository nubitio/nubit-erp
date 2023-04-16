<?php

namespace App\WebClientPrint;

class SaleItem
{
    public function __construct(
        private string         $name = '',
        private ?string        $price = '',
        private readonly ?bool $dollarSign = false
    )
    {
    }

    public function __toString()
    {
        $line_width = $_ENV['PRINTER_LINE_WIDTH'];
        $rightCols = (int)round(20 / 100 * $line_width);
        $leftCols = (int)round(80 / 100 * $line_width);
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $this->name = strlen($this->name) > $leftCols ? substr($this->name, 0, $leftCols) : $this->name;
        $left = str_pad($this->name, $leftCols);
        $sign = ($this->dollarSign ? 'S/ ' : '');
        if (is_numeric($this->price)) {
            $this->price = number_format($this->price, 2, '.', '');
        }
        $right = str_pad($sign . $this->price, $rightCols, ' ', STR_PAD_LEFT);

        return "$left$right\n";
    }
}
