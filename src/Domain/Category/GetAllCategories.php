<?php

namespace App\Domain\Category;

class GetAllCategories
{
    private ICategoryRepository $categoryRepository;

    public function __construct(ICategoryRepository $productRepository)
    {
        $this->categoryRepository = $productRepository;
    }

    public function execute(?string $filter = null): array
    {
        return $this->categoryRepository->getAll($filter);
    }
}