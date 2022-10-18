<?php

namespace App\Util;

class SaleItem
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this->name = $name;
        $this->price = $price;
        $this->dollarSign = $dollarSign;
    }

    public function __toString()
    {
        $line_width = $_ENV['PRINTER_LINE_WIDTH'];
        $rightCols = round(20 / 100 * $line_width);
        $leftCols = round(80 / 100 * $line_width);
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $this->name = strlen($this->name) > $leftCols ? substr($this->name, 0, $leftCols) : $this->name;
        $left = str_pad($this->name, $leftCols);
        $sign = ($this->dollarSign ? 'S/ ' : '');
        $right = str_pad($sign . $this->price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}
