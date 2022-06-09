<?php

namespace App\Domain\Category;

interface ICategoryRepository
{
    public function getAll(?string $filter = null): array;
}