<?php

namespace App\Recipe\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Recipe\Domain\CreateRecipeUseCase;
use App\Recipe\Domain\DeleteRecipeUseCase;
use App\Recipe\Domain\Recipe;
use App\Recipe\Domain\UpdateRecipeUseCase;

/**
 * @implements ApiProcessorInterface<Recipe>
 */
readonly class RecipeProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateRecipeUseCase $createUseCase,
        public UpdateRecipeUseCase $updateUseCase,
        public DeleteRecipeUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Recipe
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }

            return null;
        }

        if ($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
