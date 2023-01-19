<?php

namespace App\Category\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Category\Domain\Category;
use App\Category\Domain\CategoryCreateUseCase;
use App\Category\Domain\CategoryDeleteUseCase;
use App\Category\Domain\CategoryGetOneUseCase;
use App\Category\Domain\CategoryUpdateUseCase;

readonly class CategoryProcessor implements ProcessorInterface
{
    public function __construct(
        public CategoryGetOneUseCase $getOneUseCase,
        public CategoryCreateUseCase $categoryCreateUseCase,
        public CategoryUpdateUseCase $categoryUpdateUseCase,
        public CategoryDeleteUseCase $categoryDeleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Category
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->categoryDeleteUseCase->__invoke($data->categoria_id);
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->categoria_id);

        if ($exist) {
            $result = $this->categoryUpdateUseCase->__invoke($data);
        } else {
            $result = $this->categoryCreateUseCase->__invoke($data);
        }

        return $result;
    }
}