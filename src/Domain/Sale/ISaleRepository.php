<?php

namespace App\Domain\Sale;

interface ISaleRepository
{

    public function insert(Sale $sale);
}