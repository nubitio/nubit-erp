<?php

namespace App\Domain\ProductLocal;

interface IProductLocalRepository
{
    public function add(ProductLocal $productLocal): void;

    public function update(ProductLocal $productLocal): void;

    public function delete(string $productId, string $localId): void;
}