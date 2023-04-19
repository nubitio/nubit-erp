<?php

namespace App\Recipe\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Recipe\Domain\Recipe;

#[ApiResource(
    shortName: 'Recipe',
    provider: RecipeProvider::class,
    processor: RecipeProcessor::class,
)]
class RecipeDto extends Recipe
{
    #[ApiProperty(identifier: true)]
    public ?int $id;

}