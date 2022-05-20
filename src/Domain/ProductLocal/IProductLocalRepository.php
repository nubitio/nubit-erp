<?php

namespace App\Domain\ProductLocal;

interface IProductLocalRepository
{
    public function add(ProductLocal $productLocal): void;
}