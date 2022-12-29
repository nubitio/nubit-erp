<?php

namespace App\Domain\Category;

interface ICategoryRepository
{
    public function getAll(?array $filter = null): array;
}
