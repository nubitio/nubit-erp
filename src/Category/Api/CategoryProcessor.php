<?php

namespace App\Category\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Category\Domain\Category;
use App\Category\Domain\CategoryCreateUseCase;
use App\Category\Domain\CategoryDeleteUseCase;
use App\Category\Domain\CategoryUpdateUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

readonly class CategoryProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CategoryCreateUseCase $categoryCreateUseCase,
        public CategoryUpdateUseCase $categoryUpdateUseCase,
        public CategoryDeleteUseCase $categoryDeleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Category
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->categoryDeleteUseCase->__invoke($data->categoria_id);
            return null;
        }

        if ($data->categoria_id) {
            return $this->categoryUpdateUseCase->__invoke($data);
        }

        return $this->categoryCreateUseCase->__invoke($data);
    }
}